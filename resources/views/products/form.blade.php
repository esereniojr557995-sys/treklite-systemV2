@extends('layouts.app')

@php
    $editing = $product->exists;

    $variantRows = old('variants', $product->variants->map(fn ($v) => [
        'id'                  => $v->id,
        'size'                => $v->size?->name,
        'color'               => $v->color?->name,
        'price'               => $v->price,
        'low_stock_threshold' => $v->low_stock_threshold,
        'quantity'            => $v->quantity,
    ])->values()->all());
@endphp

@section('title', $editing ? 'Edit Product' : 'Add Product')

@section('content')
    <h3 class="mb-3">{{ $editing ? 'Edit Product' : 'Add Product' }}</h3>

    <form method="POST"
          action="{{ $editing ? route('products.update', $product) : route('products.store') }}"
          enctype="multipart/form-data">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="row g-3">
            {{-- Product details --}}
            <div class="col-xxl-5">
                <div class="card shadow-sm">
                    <div class="card-header fw-bold">Product details</div>
                    <div class="card-body">
                        <div class="mb-3 text-center">
                            @if ($product->image_url)
                                <img src="{{ $product->image_url }}" id="photo-preview" class="img-fluid rounded mb-2" style="max-height: 200px" alt="">
                            @else
                                @php $ph = $product->placeholder; @endphp
                                <div id="photo-placeholder" class="ph-thumb mb-2 mx-auto" style="width:200px;height:200px;font-size:96px" data-tip="{{ $ph['tip'] }}" role="img" aria-label="{{ $ph['tip'] }}"><i class="bi bi-image"></i></div>
                                <img src="" id="photo-preview" class="img-fluid rounded mb-2 d-none" style="max-height: 200px" alt="">
                            @endif
                            <input type="file" name="image" id="photo-input" accept="image/*" class="form-control form-control-sm">
                            <div class="form-text">Product photo (JPG/PNG, up to 4 MB).</div>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Model</label>
                            <input type="text" name="name" id="model-name" class="form-control" value="{{ old('name', $product->name) }}" required placeholder="e.g. Trail Slippers">
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label" for="category-select">Category</label>
                                <div class="input-group">
                                    <select name="category" id="category-select" class="form-select">
                                        <option value="">&mdash; None &mdash;</option>
                                        @foreach ($categories as $c)<option value="{{ $c }}" @selected(old('category', $product->category?->name) === $c)>{{ $c }}</option>@endforeach
                                    </select>
                                    <button type="button" class="btn btn-outline-success" data-quick="categories" title="Add a new category" aria-label="Add a new category"><i class="bi bi-plus-lg"></i></button>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="brand-select">Brand</label>
                                <div class="input-group">
                                    <select name="brand" id="brand-select" class="form-select">
                                        <option value="">&mdash; None &mdash;</option>
                                        @foreach ($brands as $b)<option value="{{ $b }}" @selected(old('brand', $product->brand?->name) === $b)>{{ $b }}</option>@endforeach
                                    </select>
                                    <button type="button" class="btn btn-outline-success" data-quick="brands" title="Add a new brand" aria-label="Add a new brand"><i class="bi bi-plus-lg"></i></button>
                                </div>
                            </div>
                            <div class="col-12 form-text mt-1">
                                Pick from the options, or press <i class="bi bi-plus-lg"></i> to add a new one.
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Price (₱)</label>
                            <input type="number" step="0.01" min="0" name="price" class="form-control" value="{{ old('price', $product->price) }}" required>
                            <div class="form-text">Default price. A variant can have its own price.</div>
                        </div>
                        <div>
                            <label class="form-label">Description</label>
                            <textarea name="description" rows="3" class="form-control">{{ old('description', $product->description) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Variants --}}
            <div class="col-xxl-7">
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-bold">Variants (size / color)</span>
                        <button type="button" class="btn btn-outline-success btn-icon" id="add-variant" title="Add variant (another size / color)" aria-label="Add variant"><i class="bi bi-plus-lg"></i></button>
                    </div>
                    <div class="card-body">
                        <div class="small text-muted mb-2">
                            Each size/color combination is one row. For items with no size or color, keep a single row and leave both blank.
                            The first column shows the <strong>Model</strong> (read-only, it follows what you type on the left). The system keeps its own item code, so there is nothing else to type.
                            Type the <strong>Starting stock</strong> (at least 1) for each new row. The product, its sizes / colors and its stock are saved together in one step, and the stock is recorded in Stock History.
                            @if ($editing)
                                Stock of existing rows is not changed here. Use <strong>Inventory</strong> (Receive / Count) so every change is logged. Press <strong>X</strong> to remove a row you added by mistake (not possible if it was already sold).
                            @endif
                        </div>
                        <div class="variants-wrap">
                            <style>
                                /* Every column has a fixed share of the width, so the Size / Color drop-downs, Price, Low at and
                                   Starting stock fields are all the same height and their words ("none", "default") are always fully visible. */
                                #variants-table { table-layout: fixed; width: 100%; }
                                #variants-table th, #variants-table td { padding-left: .25rem; padding-right: .25rem; }
                                @media (max-width: 575.98px) { .variants-wrap { overflow-x: auto; } #variants-table { min-width: 560px; } }   /* phones only */
                                #variants-table th { line-height: 1.15; }
                                #variants-table th, #variants-table td { vertical-align: middle; }
                                #variants-table .form-select, #variants-table .form-control { width: 100%; height: 34px; }
                                #variants-table .form-select { padding-left: .5rem; padding-right: 1.5rem; text-overflow: ellipsis; }
                                #variants-table .form-control { padding-left: .5rem; padding-right: .4rem; }
                                #variants-table .remove-variant { margin: 0 auto; }
                            </style>
                            <table class="table table-sm align-middle" id="variants-table">
                                <colgroup>
                                    <col style="width:19%"><col style="width:17%"><col style="width:17%"><col style="width:13%">
                                    <col style="width:10%"><col style="width:14%"><col style="width:46px">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>Model</th>
                                        <th>Size <button type="button" class="btn btn-outline-success btn-sm py-0 px-1 ms-1" data-quick="sizes" title="Add a new size" aria-label="Add a new size"><i class="bi bi-plus-lg"></i></button></th>
                                        <th>Color <button type="button" class="btn btn-outline-success btn-sm py-0 px-1 ms-1" data-quick="colors" title="Add a new color" aria-label="Add a new color"><i class="bi bi-plus-lg"></i></button></th>
                                        <th>Price</th>
                                        <th>Low at</th>
                                        <th>Starting stock</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <button class="btn btn-success btn-icon" title="{{ $editing ? 'Save changes' : 'Save product' }}" aria-label="{{ $editing ? 'Save changes' : 'Save product' }}"><i class="bi bi-check-lg"></i></button>
            <a href="{{ route('products.index') }}" class="btn btn-outline-secondary btn-icon" title="Cancel" aria-label="Cancel"><i class="bi bi-x-lg"></i></a>
        </div>
    </form>

    <script>
        const EXISTING = @json($variantRows);
        const EDITING = @json((bool) $editing);
        const LISTS = { sizes: @json($sizes->values()), colors: @json($colors->values()) };
        const OPTIONS_URL = @json(url('/options'));
        const CSRF = @json(csrf_token());

        const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const tbody = document.querySelector('#variants-table tbody');
        let n = 0;

        // The Model typed on the left is shown (read-only) in the first column of every size/color row.
        const modelInput = document.getElementById('model-name');
        modelInput.addEventListener('input', () => {
            document.querySelectorAll('.model-mirror').forEach(el => { el.value = modelInput.value; });
        });

        // A drop-down for the size / color of one row (an unknown saved value is still shown, never lost).
        function pickerHtml(type, name, value) {
            const list = LISTS[type].includes(value) || !value ? LISTS[type] : [...LISTS[type], value];
            return `<select name="${name}" class="form-select form-select-sm ${type}-select" data-type="${type}">
                <option value="">None</option>
                ${list.map(x => `<option value="${esc(x)}" ${x === value ? 'selected' : ''}>${esc(x)}</option>`).join('')}
            </select>`;
        }

        function addVariant(v = {}) {
            const i = n++;
            const isNew = !v.id;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    ${v.id ? `<input type="hidden" name="variants[${i}][id]" value="${esc(v.id)}">` : ''}
                    <input type="text" class="form-control form-control-sm bg-light model-mirror" value="${esc(modelInput.value)}" placeholder="Model" readonly tabindex="-1" aria-label="Model (read-only)">
                </td>
                <td>${pickerHtml('sizes', `variants[${i}][size]`, v.size)}</td>
                <td>${pickerHtml('colors', `variants[${i}][color]`, v.color)}</td>
                <td><input type="number" step="0.01" min="0" name="variants[${i}][price]" class="form-control form-control-sm" value="${esc(v.price)}" placeholder="Default" aria-label="Price"></td>
                <td><input type="number" min="0" name="variants[${i}][low_stock_threshold]" class="form-control form-control-sm" value="${esc(v.low_stock_threshold ?? 5)}" placeholder="5" required aria-label="Low stock at"></td>
                <td>${isNew
                    ? `<input type="number" min="1" max="1000000" name="variants[${i}][initial_stock]" class="form-control form-control-sm" value="${esc(v.initial_stock ?? 1)}" placeholder="Stock" required aria-label="Starting stock">`
                    : `<span class="text-muted">${esc(v.quantity ?? 0)}</span>`}</td>
                <td><button type="button" class="btn btn-outline-danger btn-icon remove-variant" ${isNew ? '' : 'data-existing="1"'} title="Remove this size / color" aria-label="Remove this size / color"><i class="bi bi-x-lg"></i></button></td>`;
            tbody.appendChild(tr);
        }

        document.getElementById('add-variant').addEventListener('click', () => addVariant());
        tbody.addEventListener('click', e => {
            const btn = e.target.closest('.remove-variant');
            if (!btn) return;
            if (tbody.children.length <= 1) { alert('A product needs at least one row. Edit this row instead, or add the new row first.'); return; }
            const tr = btn.closest('tr');
            // A row that is already saved is only removed when the form is saved, and only if it was never sold.
            if (btn.dataset.existing && !confirm('Remove this size / color from the product?\nIts stock history is removed too. This cannot be done if it was already sold.\nIt is only removed when you press Save.')) return;
            tr.remove();
        });

        (EXISTING.length ? EXISTING : [{}]).forEach(addVariant);

        // Live photo preview
        document.getElementById('photo-input').addEventListener('change', e => {
            const f = e.target.files[0];
            if (!f) return;
            const img = document.getElementById('photo-preview');
            img.src = URL.createObjectURL(f);
            img.classList.remove('d-none');
            document.getElementById('photo-placeholder')?.classList.add('d-none');
        });
    </script>

    {{-- ===== Quick-add pop-up (brand / category / size / color) ===== --}}
    <div class="modal fade" id="quick-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <form class="modal-content" id="quick-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="quick-title">Add</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2 d-none" id="quick-error"></div>
                    <label class="form-label" for="quick-name" id="quick-label">Name</label>
                    <input type="text" class="form-control" id="quick-name" maxlength="255" autocomplete="off" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-icon" data-bs-dismiss="modal" title="Cancel" aria-label="Cancel"><i class="bi bi-x-lg"></i></button>
                    <button type="submit" class="btn btn-success btn-icon" id="quick-save" title="Add" aria-label="Add"><i class="bi bi-check-lg"></i></button>
                </div>
            </form>
        </div>
    </div>

    <script>
        window.addEventListener('load', () => {
            const NAMES = { brands: 'brand', categories: 'category', sizes: 'size', colors: 'color' };
            const modal = new bootstrap.Modal('#quick-modal');
            const nameEl = document.getElementById('quick-name'), errEl = document.getElementById('quick-error'), saveBtn = document.getElementById('quick-save');
            let type = null;
            const lastFocused = {};   // which size / color drop-down was used last, so the new entry lands there

            document.addEventListener('focusin', e => { if (e.target.matches?.('.sizes-select, .colors-select')) lastFocused[e.target.dataset.type] = e.target; });

            document.addEventListener('click', e => {
                const b = e.target.closest('[data-quick]');
                if (!b) return;
                type = b.dataset.quick;
                document.getElementById('quick-title').textContent = 'Add ' + NAMES[type];
                document.getElementById('quick-label').textContent = 'New ' + NAMES[type] + ' name';
                nameEl.maxLength = (type === 'sizes' || type === 'colors') ? 50 : 255;
                nameEl.value = '';
                nameEl.style.textTransform = type === 'brands' ? 'uppercase' : '';   // brands are typed (and saved) in capitals
                errEl.classList.add('d-none');
                saveBtn.disabled = false;
                modal.show();
            });
            document.getElementById('quick-modal').addEventListener('shown.bs.modal', () => nameEl.focus());

            function insert(select, name, sorted) {
                if ([...select.options].some(o => o.value === name)) return;
                const opt = new Option(name, name);
                const after = sorted ? [...select.options].slice(1).find(o => o.value.localeCompare(name, undefined, { sensitivity: 'base' }) > 0) : null;
                select.add(opt, after ?? null);
            }

            document.getElementById('quick-form').addEventListener('submit', async e => {
                e.preventDefault();
                errEl.classList.add('d-none');
                saveBtn.disabled = true;
                try {
                    const res = await fetch(OPTIONS_URL + '/' + type, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                        body: JSON.stringify({ name: nameEl.value }),
                    });
                    const json = await res.json().catch(() => ({}));
                    if (!res.ok) throw new Error(json.errors ? Object.values(json.errors).flat().join(' ') : (json.message || 'Could not add it. Please try again.'));

                    const name = json.name;
                    if (type === 'brands' || type === 'categories') {
                        const sel = document.getElementById(type === 'brands' ? 'brand-select' : 'category-select');
                        insert(sel, name, true);
                        sel.value = name;
                    } else {
                        LISTS[type].push(name);
                        document.querySelectorAll('.' + type + '-select').forEach(sel => insert(sel, name, type === 'colors'));
                        const target = lastFocused[type] ?? [...document.querySelectorAll('.' + type + '-select')].find(s => s.value === '');
                        if (target) target.value = name;
                    }
                    modal.hide();
                } catch (err) {
                    errEl.textContent = err.message;
                    errEl.classList.remove('d-none');
                    saveBtn.disabled = false;
                }
            });
        });
    </script>
@endsection
