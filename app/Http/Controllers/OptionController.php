<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Size;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Owner only: the options used by products (Brands, Categories, Sizes, Colors).
 * Products can only choose from these lists, so a name is never mistyped or duplicated.
 * Add / rename / disable / delete all happen from pop-up forms on one page.
 */
class OptionController extends Controller
{
    /** Everything that differs between the four lists. */
    private const TYPES = [
        'brands'     => ['model' => Brand::class,    'one' => 'Brand',    'many' => 'Brands',     'icon' => 'bi-bookmark-star', 'uses' => 'products', 'max' => 255],
        'categories' => ['model' => Category::class, 'one' => 'Category', 'many' => 'Categories', 'icon' => 'bi-diagram-3',     'uses' => 'products', 'max' => 255],
        'sizes'      => ['model' => Size::class,     'one' => 'Size',     'many' => 'Sizes',      'icon' => 'bi-rulers',        'uses' => 'variants', 'max' => 50],
        'colors'     => ['model' => Color::class,    'one' => 'Color',    'many' => 'Colors',     'icon' => 'bi-palette',       'uses' => 'variants', 'max' => 50],
    ];

    public function index(Request $request)
    {
        $tab = array_key_exists($request->query('tab'), self::TYPES) ? $request->query('tab') : 'brands';

        $lists = [];
        foreach (self::TYPES as $key => $t) {
            $query = $t['model']::query()->withCount($t['uses'] . ' as used_count');
            $key === 'sizes' ? $query->orderBy('sort_order')->orderBy('name') : $query->orderBy('name');
            $lists[$key] = $query->get();
        }

        return view('options.index', ['types' => self::TYPES, 'lists' => $lists, 'tab' => $tab]);
    }

    public function store(Request $request, string $type)
    {
        $t    = $this->type($type);
        $data = $this->validated($request, $type);

        $row = $t['model']::create($this->fields($type, $data, true));

        ActivityLog::record('option_add', 'Added ' . strtolower($t['one']) . ' "' . $row->name . '".');

        return $this->done($request, $t['one'] . ' "' . $row->name . '" added.', ['id' => $row->id, 'name' => $row->name]);
    }

    public function update(Request $request, string $type, int $id)
    {
        $t    = $this->type($type);
        $row  = $t['model']::findOrFail($id);
        $data = $this->validated($request, $type, $row->id);
        $old  = $row->name;

        $row->update($this->fields($type, $data, false));

        ActivityLog::record('option_edit', 'Edited ' . strtolower($t['one']) . ' "' . $old . '"' . ($old !== $row->name ? ' -> "' . $row->name . '"' : '') . '.');

        return $this->done($request, $t['one'] . ' updated.', ['id' => $row->id, 'name' => $row->name]);
    }

    /** Disable = hide from the pick options, but products that already use it keep it. Can be switched back on. */
    public function toggle(Request $request, string $type, int $id)
    {
        $t   = $this->type($type);
        $row = $t['model']::findOrFail($id);
        $row->update(['is_active' => ! $row->is_active]);

        ActivityLog::record('option_toggle', ($row->is_active ? 'Enabled ' : 'Disabled ') . strtolower($t['one']) . ' "' . $row->name . '".');

        return $this->done($request, $t['one'] . ' "' . $row->name . '" is now ' . ($row->is_active ? 'enabled.' : 'disabled (hidden from new products).'));
    }

    /** Only an unused entry can be deleted. One that products already use must be disabled instead. */
    public function destroy(Request $request, string $type, int $id)
    {
        $t    = $this->type($type);
        $row  = $t['model']::findOrFail($id);
        $used = $row->{$t['uses']}()->count();

        if ($used > 0) {
            $msg = $t['one'] . ' "' . $row->name . '" is used by ' . $used . ' ' . $t['uses'] . ', so it cannot be deleted. Disable it instead.';

            return $request->expectsJson()
                ? response()->json(['message' => $msg], 422)
                : back()->withErrors(['option' => $msg]);
        }

        $name = $row->name;
        $row->delete();

        ActivityLog::record('option_delete', 'Deleted ' . strtolower($t['one']) . ' "' . $name . '".');

        return $this->done($request, $t['one'] . ' "' . $name . '" deleted.');
    }

    private function type(string $type): array
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return self::TYPES[$type];
    }

    /** Name is required, and the same name (ignoring capitals) cannot exist twice. */
    private function validated(Request $request, string $type, ?int $ignoreId = null): array
    {
        $t     = self::TYPES[$type];
        $rules = ['name' => ['required', 'string', 'max:' . $t['max']]];

        if ($type === 'sizes') {
            $rules['sort_order'] = ['nullable', 'integer', 'min:0', 'max:100000'];
        }

        $request->merge(['name' => trim(preg_replace('/\s+/', ' ', (string) $request->input('name')))]);
        $data = $request->validate($rules);

        $exists = $t['model']::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'name' => $t['one'] . ' "' . $data['name'] . '" already exists.',
            ]);
        }

        return $data;
    }

    private function fields(string $type, array $data, bool $creating): array
    {
        $fields = ['name' => $data['name']];

        if ($type === 'sizes') {
            if (($data['sort_order'] ?? '') !== '') {
                $fields['sort_order'] = (int) $data['sort_order'];
            } elseif ($creating) {
                $fields['sort_order'] = ((int) Size::max('sort_order')) + 1;   // new sizes go to the end of the list
            }
        }

        return $fields;
    }

    /** Pop-ups talk JSON (so errors stay inside the pop-up); plain requests are redirected back. */
    private function done(Request $request, string $message, array $extra = []): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        if ($request->expectsJson()) {
            // Only the Item Options page reloads itself afterwards, so only it asks for the message to be kept.
            if ($request->header('X-Reload')) {
                session()->flash('status', $message);
            }

            return response()->json(['message' => $message] + $extra);
        }

        return back()->with('status', $message);
    }
}
