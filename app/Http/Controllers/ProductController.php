<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Image;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SaleItem;
use App\Models\Size;
use App\Models\StockMovement;
use App\Support\ImageStore;
use App\Support\Lookup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    /**
     * Product list. Every product is loaded ONCE; searching, the brand / category / status filters and the paging
     * all happen in the browser (see data-ctable in the layout), so changing a filter never queries the server again.
     */
    public function index()
    {
        $products = Product::with('variants', 'brand', 'category')
            ->orderBy('name')
            ->get();

        $brands     = Brand::orderBy('name')->pluck('name');
        $categories = Category::orderBy('name')->pluck('name');

        // Products with no sales and no stock records at all (e.g. created by mistake) can be deleted.
        $variantRows = $products->flatMap->variants;
        $usedIds     = SaleItem::whereIn('variant_id', $variantRows->pluck('id'))->pluck('variant_id')
            ->merge(StockMovement::whereIn('variant_id', $variantRows->pluck('id'))->pluck('variant_id'))
            ->unique();
        $deletable = $products->filter(fn ($p) => $p->variants->pluck('id')->intersect($usedIds)->isEmpty())->pluck('id')->values();

        return view('products.index', compact('products', 'brands', 'categories', 'deletable'));
    }

    public function create()
    {
        return view('products.form', ['product' => new Product()] + $this->pickLists(new Product()));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $product = DB::transaction(function () use ($request, $data) {
            $product = Product::create($this->productFields($request, $data));
            $this->saveVariants($product, $data['variants']);

            return $product;
        });

        ActivityLog::record('product_add', 'Added product ' . $product->name . ' (price ₱' . number_format((float) $product->price, 2) . ', ' . count($data['variants']) . ' size/color row(s)).');

        $units = collect($data['variants'])->sum(fn ($r) => (int) ($r['initial_stock'] ?? 0));

        // One step: the product, its sizes / colors and its opening stock are all saved together.
        return redirect()->route('products.index')
            ->with('status', $product->name . ' was added with ' . number_format($units) . ' unit(s) in stock. Use Inventory (Receive / Count) for later changes.');
    }

    public function edit(Product $product)
    {
        $product->load('variants', 'brand', 'category');

        return view('products.form', ['product' => $product] + $this->pickLists($product));
    }

    public function update(Request $request, Product $product)
    {
        $data     = $this->validated($request);
        $oldName  = $product->name;
        $oldPrice = (float) $product->price;

        $removed = [];

        DB::transaction(function () use ($request, $data, $product, &$removed) {
            $oldImageId = $product->image_id;
            $fields     = $this->productFields($request, $data);

            $product->update($fields);
            $removed = $this->saveVariants($product, $data['variants']);

            // A new photo replaces the old one, so the old photo is removed from the database.
            if (isset($fields['image_id']) && $oldImageId && $oldImageId !== $fields['image_id']) {
                Image::whereKey($oldImageId)->delete();
            }
        });

        $note = '';
        if (round($oldPrice, 2) !== round((float) $data['price'], 2)) {
            $note .= ' Price ₱' . number_format($oldPrice, 2) . ' -> ₱' . number_format((float) $data['price'], 2) . '.';
        }
        if ($oldName !== $data['name']) {
            $note .= ' Renamed from "' . $oldName . '".';
        }
        if ($removed) {
            $note .= ' Removed size/color: ' . implode(', ', $removed) . '.';
        }
        ActivityLog::record('product_edit', 'Edited product ' . $data['name'] . '.' . $note);

        return redirect()->route('products.index')->with('status', 'Product updated.');
    }

    /** Archive = stop selling and hide from stock lists, but keep every past sale and stock record. */
    public function archive(Product $product)
    {
        $product->update(['is_active' => false]);
        ActivityLog::record('product_archive', 'Archived product ' . $product->name . '.');

        return redirect()->route('products.index')->with('status', $product->name . ' was archived. It no longer appears in New Sale or Inventory. You can restore it anytime.');
    }

    /** Only for a product that has never been sold and never had stock recorded. Everything else must be archived. */
    public function destroy(Product $product)
    {
        $ids = $product->variants()->pluck('id');

        if (SaleItem::whereIn('variant_id', $ids)->exists() || StockMovement::whereIn('variant_id', $ids)->exists()) {
            return back()->withErrors(['product' => $product->name . ' already has sales or stock records, so it cannot be deleted. Archive it instead.']);
        }

        $name = $product->name;

        DB::transaction(function () use ($product) {
            $imageId = $product->image_id;
            $product->variants()->delete();
            $product->delete();
            if ($imageId) {
                Image::whereKey($imageId)->delete();
            }
        });

        ActivityLog::record('product_delete', 'Deleted product ' . $name . ' (it had no sales or stock records).');

        return redirect()->route('products.index')->with('status', $name . ' was deleted.');
    }

    public function restore(Product $product)
    {
        $product->update(['is_active' => true]);
        ActivityLog::record('product_restore', 'Restored product ' . $product->name . '.');

        return redirect()->route('products.index', ['status' => 'archived'])->with('status', $product->name . ' is active again.');
    }

    /**
     * The choices shown in the product form: every enabled entry, plus whatever this product already uses
     * (so a disabled brand / size / color that is still on an existing product does not disappear from its form).
     */
    private function pickLists(Product $product): array
    {
        $usedSizes  = $product->exists ? $product->variants->pluck('size_id')->filter() : collect();
        $usedColors = $product->exists ? $product->variants->pluck('color_id')->filter() : collect();

        return [
            'brands'     => Brand::where(fn ($w) => $w->where('is_active', true)->orWhere('id', $product->brand_id))->orderBy('name')->pluck('name'),
            'categories' => Category::where(fn ($w) => $w->where('is_active', true)->orWhere('id', $product->category_id))->orderBy('name')->pluck('name'),
            'sizes'      => Size::where(fn ($w) => $w->where('is_active', true)->orWhereIn('id', $usedSizes))->orderBy('sort_order')->orderBy('name')->pluck('name'),
            'colors'     => Color::where(fn ($w) => $w->where('is_active', true)->orWhereIn('id', $usedColors))->orderBy('name')->pluck('name'),
        ];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'                         => ['required', 'string', 'max:255'],
            // Brand, category, size and color must already exist in the Item Options page (no free typing = no typos).
            'category'                     => ['nullable', 'string', 'max:255', Rule::exists('categories', 'name')],
            'brand'                        => ['nullable', 'string', 'max:255', Rule::exists('brands', 'name')],
            'description'                  => ['nullable', 'string', 'max:2000'],
            'price'                        => ['required', 'numeric', 'min:0'],
            'image'                        => ['nullable', 'image', 'max:4096'],
            'variants'                     => ['required', 'array', 'min:1'],
            'variants.*.id'                => ['nullable', 'integer'],
            'variants.*.size'              => ['nullable', 'string', 'max:50', Rule::exists('sizes', 'name')],
            'variants.*.color'             => ['nullable', 'string', 'max:50', Rule::exists('colors', 'name')],
            'variants.*.price'             => ['nullable', 'numeric', 'min:0'],
            'variants.*.initial_stock'     => ['nullable', 'integer', 'min:0', 'max:1000000'],   // new rows must be >= 1 (checked below)
            'variants.*.low_stock_threshold' => ['required', 'integer', 'min:0'],
        ]);

        // Adding a product (or a new size / color row) and its stock is ONE step, so every NEW row must start with at least 1 unit.
        // (Rows that already exist keep their stock; it is changed only in Inventory so every change is logged.)
        $errors = [];
        foreach ($data['variants'] as $i => $row) {
            if (empty($row['id']) && (int) ($row['initial_stock'] ?? 0) < 1) {
                $errors["variants.$i.initial_stock"] = 'Enter the starting stock (at least 1) for this size / color.';
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }

    /**
     * Fields saved on the product. Brand and category are typed names: an existing one is reused,
     * a new one is added to the brands / categories table, and empty means "none".
     */
    private function productFields(Request $request, array $data): array
    {
        $fields = collect($data)->only(['name', 'description', 'price'])->all();

        $fields['brand_id']    = Lookup::id(Brand::class, $data['brand'] ?? null);
        $fields['category_id'] = Lookup::id(Category::class, $data['category'] ?? null);

        // The photo is saved inside the database (not as a file on the server), so it is not lost on restart.
        if ($request->hasFile('image')) {
            $fields['image_id'] = ImageStore::fromUpload($request->file('image'), 1200);
        }

        return $fields;
    }

    /** Saves the rows. Returns the names of the size/color rows that were removed (e.g. ["Size 42 / Black (3 unit(s))"]). */
    private function saveVariants(Product $product, array $rows): array
    {
        // The same size + color cannot be entered twice for one product.
        $seen = [];
        foreach ($rows as $i => $row) {
            $key = mb_strtolower(trim(($row['size'] ?? '') . '|' . ($row['color'] ?? '')));
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(["variants.$i.size" => 'This size / color is entered twice. Combine them into one row.']);
            }
            $seen[$key] = true;
        }

        // A row taken out of the form (the X button) is removed from the product. A size/color that was already SOLD
        // must stay, so the past receipts keep working: set its stock to 0 in Inventory instead. Its own stock history goes with it.
        $removed = [];
        $keep    = collect($rows)->pluck('id')->filter()->map(fn ($v) => (int) $v)->all();
        foreach ($product->variants()->whereNotIn('id', $keep)->get() as $old) {
            if (SaleItem::where('variant_id', $old->id)->exists()) {
                throw ValidationException::withMessages(['variants' => '"' . $old->label . '" was already sold, so it cannot be removed. Keep the row and set its stock to 0 in Inventory instead.']);
            }
            $removed[] = $old->label . ' (' . (int) $old->quantity . ' unit(s))';
            $old->delete();   // its stock history is deleted with it
        }

        foreach ($rows as $i => $row) {
            $id = $row['id'] ?? null;

            $attrs = [
                'size_id'             => Lookup::id(Size::class, $row['size'] ?? null),
                'color_id'            => Lookup::id(Color::class, $row['color'] ?? null),
                'price'               => ($row['price'] ?? '') === '' ? null : $row['price'],
                'low_stock_threshold' => $row['low_stock_threshold'],
            ];

            if ($id) {
                // Quantity is never edited here; use Inventory > Receive / Adjust so every change is logged.
                $product->variants()->whereKey($id)->update($attrs);
                continue;
            }

            // A new item starts with its initial stock (0 if none is entered). The initial stock is logged
            // in Stock History like any other delivery.
            $initial = (int) ($row['initial_stock'] ?? 0);
            $variant = $product->variants()->create($attrs + ['initial_stock' => $initial, 'quantity' => $initial]);

            if ($initial > 0) {
                StockMovement::create([
                    'variant_id'      => $variant->id,
                    'user_id'         => auth()->id(),
                    'type'            => 'receive',
                    'quantity_before' => 0,
                    'quantity_change' => $initial,
                    'quantity_after'  => $initial,
                    'note'            => 'Initial stock',
                ]);
            }
        }

        return $removed;
    }
}
