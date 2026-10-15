<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    /** Value used in the URL for products that have no brand. */
    public const NO_BRAND = '__none';

    /**
     * Inventory has two views:
     *  1. Brand overview (no filter chosen) - one card per brand, so the page is not full of repeated items.
     *  2. Item list (a brand, a product, a search or a filter is chosen) - one row per size/color.
     * Everyone can look up availability; only the Owner can change stock.
     */
    public function index(Request $request)
    {
        $q        = trim((string) $request->query('q'));
        $brand    = (string) $request->query('brand', '');
        $category = trim((string) $request->query('category'));
        $size     = trim((string) $request->query('size'));
        $color    = trim((string) $request->query('color'));
        $status   = in_array($request->query('status'), ['out', 'low', 'ok'], true) ? $request->query('status') : '';
        $product  = $request->filled('product') ? Product::find((int) $request->query('product')) : null;

        $all      = $request->query('view') === 'all';   // "All items" view: every item in one list, no brand chosen

        $filtering = $all || $q !== '' || $brand !== '' || $category !== '' || $size !== '' || $color !== '' || $status !== '' || $product;

        // ---------- 1. Brand overview ----------
        if (! $filtering) {
            $brands = Product::query()
                ->where('products.is_active', true)
                ->leftJoin('brands as b', 'b.id', '=', 'products.brand_id')
                ->leftJoin('product_variants as v', 'v.product_id', '=', 'products.id')
                ->selectRaw("COALESCE(b.name, '') as brand_name")
                ->selectRaw('COUNT(DISTINCT products.id) as product_count')
                ->selectRaw('COALESCE(SUM(v.quantity), 0) as units')
                ->selectRaw('COALESCE(SUM(CASE WHEN v.id IS NOT NULL AND v.quantity <= 0 THEN 1 ELSE 0 END), 0) as out_count')
                ->selectRaw('COALESCE(SUM(CASE WHEN v.quantity > 0 AND v.quantity <= v.low_stock_threshold THEN 1 ELSE 0 END), 0) as low_count')
                ->selectRaw('COALESCE(SUM(CASE WHEN v.quantity > v.low_stock_threshold THEN 1 ELSE 0 END), 0) as ok_count')
                ->groupBy('brand_name')
                ->orderBy('brand_name')
                ->get();

            // Words each brand card can be found by (brand, model, category, size, color). The brand overview is loaded ONCE
            // and its search box / Status filter work in the browser, so they never query the server again.
            $brandWords = Product::where('is_active', true)
                ->with('brand', 'category', 'variants.size', 'variants.color')
                ->get()
                ->groupBy(fn ($p) => $p->brand?->name ?? '')
                ->map(fn ($g) => mb_strtolower(
                    $g->flatMap(fn ($p) => array_merge(
                        [$p->brand?->name, $p->name, $p->category?->name],
                        $p->variants->flatMap(fn ($v) => [$v->size?->name, $v->color?->name])->all()
                    ))->filter()->unique()->implode(' ')
                ));

            return view('inventory.index', [
                'mode'   => 'brands',
                'brands' => $brands,
                'brandWords' => $brandWords,
                'q' => '', 'brand' => '', 'category' => '', 'size' => '', 'color' => '', 'status' => '', 'product' => null, 'all' => false,
            ]);
        }

        // ---------- 2. Item list ----------
        $scope = ProductVariant::query()
            ->whereHas('product', fn ($p) => $p->where('is_active', true))
            ->when($product, fn ($w) => $w->where('product_id', $product->id))
            ->when($brand !== '', fn ($w) => $w->whereHas('product', fn ($p) => $this->brandFilter($p, $brand)));

        // Options for the filter drop-downs come from what is inside the current brand / product.
        $sizes  = Size::whereIn('id', (clone $scope)->whereNotNull('size_id')->select('size_id'))->orderBy('sort_order')->orderBy('name')->pluck('name');
        $colors = Color::whereIn('id', (clone $scope)->whereNotNull('color_id')->select('color_id'))->orderBy('name')->pluck('name');
        $categories = Category::whereIn('id', Product::query()
            ->where('is_active', true)
            ->when($product, fn ($w) => $w->whereKey($product->id))
            ->when($brand !== '', fn ($w) => $this->brandFilter($w, $brand))
            ->whereNotNull('category_id')
            ->select('category_id'))
            ->orderBy('name')->pluck('name');

        // Every item of this brand / product is loaded ONCE. The search box, the category / size / color / status
        // filters and the paging all work in the browser (data-ctable), so they never query the server again.
        $variants = (clone $scope)->with('product.brand', 'product.category')
            ->orderBy('product_id')
            ->orderBy('id')
            ->get();

        // Brand drop-down for the "All items" view (so the full list can still be narrowed to one brand).
        $brandOptions = Brand::orderBy('name')->pluck('name');
        $hasNoBrand   = Product::where('is_active', true)->whereNull('brand_id')->exists();

        return view('inventory.index', [
            'mode' => 'items',
            'all' => $all, 'brandOptions' => $brandOptions, 'hasNoBrand' => $hasNoBrand,
            'variants' => $variants,
            'sizes' => $sizes, 'colors' => $colors, 'categories' => $categories,
            'q' => $q, 'brand' => $brand, 'category' => $category, 'size' => $size, 'color' => $color,
            'status' => $status, 'product' => $product,
        ]);
    }

    /**
     * One form for both actions: Receive (a delivery arrived, add it) or Count (set to the number physically counted).
     * A count never silently overwrites: what the system expected, what was counted and the difference are all kept.
     */
    public function stock(Request $request, ProductVariant $variant)
    {
        $data = $request->validate([
            'mode'     => ['required', 'in:receive,count'],
            'quantity' => ['required', 'integer', 'min:0'],
            'note'     => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['mode'] === 'receive' && $data['quantity'] < 1) {
            throw ValidationException::withMessages(['quantity' => ['Enter how many units were received (at least 1).']]);
        }

        abort_unless($variant->product->is_active, 404);

        $label  = $variant->product->name . ' (' . $variant->label . ')';
        $result = DB::transaction(function () use ($variant, $data) {
            $v      = ProductVariant::lockForUpdate()->findOrFail($variant->id);
            $before = (int) $v->quantity;
            $note   = trim((string) ($data['note'] ?? ''));

            if ($data['mode'] === 'receive') {
                $after = $before + $data['quantity'];
                $type  = 'receive';
                $note  = $note !== '' ? $note : 'Delivery received';
            } else {
                $after = (int) $data['quantity'];
                $type  = 'count';
                if ($after !== $before && $note === '') {
                    throw ValidationException::withMessages(['note' => ['The count does not match the system (' . $before . '). Please add a note explaining the difference.']]);
                }
                $note = $note !== '' ? $note : 'Physical count - matches the system';
            }

            $v->update(['quantity' => $after]);

            StockMovement::create([
                'variant_id'      => $v->id,
                'user_id'         => auth()->id(),
                'type'            => $type,
                'quantity_before' => $before,
                'quantity_change' => $after - $before,
                'quantity_after'  => $after,
                'note'            => $note,
            ]);

            return compact('before', 'after');
        });

        if ($data['mode'] === 'receive') {
            $msg = "Received {$data['quantity']} unit(s) of {$label}. Stock is now {$result['after']}.";
        } else {
            $diff = $result['after'] - $result['before'];
            $msg  = $diff === 0
                ? "Count recorded for {$label}: {$result['after']} - matches the system."
                : "Count recorded for {$label}: system had {$result['before']}, counted {$result['after']} (" . ($diff > 0 ? 'over by ' . $diff : 'short by ' . abs($diff)) . '). Stock updated and the difference is saved in Stock History.';
        }

        return back()->with('status', $msg);
    }

    /** Full trail of every stock change: deliveries, counts (with expected vs counted) and sales. */
    public function history(Request $request)
    {
        $variant = $request->filled('variant') ? ProductVariant::with('product')->find((int) $request->query('variant')) : null;
        $product = $request->filled('product') ? Product::find((int) $request->query('product')) : null;
        $type    = in_array($request->query('type'), ['receive', 'count', 'sale', 'void'], true) ? $request->query('type') : '';
        $issues  = $request->boolean('issues');   // only used to pre-select the filters

        // Latest 2,000 movements are loaded once; type / "did not match" filters and paging happen in the browser.
        $movements = StockMovement::with('variant.product', 'user')
            ->when($variant, fn ($w) => $w->where('variant_id', $variant->id))
            ->when($product, fn ($w) => $w->whereHas('variant', fn ($v) => $v->where('product_id', $product->id)))
            ->latest('id')
            ->limit(2000)
            ->get();

        return view('inventory.history', compact('movements', 'variant', 'product', 'type', 'issues'));
    }

    /** Brand filter that understands the "no brand" group. */
    private function brandFilter($query, string $brand)
    {
        return $brand === self::NO_BRAND
            ? $query->whereNull('brand_id')
            : $query->whereHas('brand', fn ($b) => $b->where('name', $brand));
    }
}
