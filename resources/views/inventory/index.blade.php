@extends('layouts.app')

@section('title', 'Inventory')

@section('content')
    @php
        $isOwner = auth()->user()->hasFullAccess();
        $noBrand = \App\Http\Controllers\InventoryController::NO_BRAND;
    @endphp

    <style>
        .status-badge { display: inline-block; width: 90px; }
        .brand-card { transition: box-shadow .15s, transform .15s, border-color .15s; border: 1px solid #e3e6e4; }
        .brand-card:hover { border-color: #1e5b31; box-shadow: 0 .4rem .9rem rgba(0,0,0,.12); transform: translateY(-2px); }
    </style>

    {{-- ===== Header: title + search ===== --}}
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h3 class="mb-0">Inventory</h3>
        <div class="d-flex gap-2 flex-wrap">
            @if ($isOwner)
                <a href="{{ route('inventory.history') }}" class="btn btn-outline-secondary btn-icon" title="Stock History" aria-label="Stock History"><i class="bi bi-clock-history"></i></a>
                <a href="{{ route('products.create') }}" class="btn btn-outline-success btn-icon" title="Add Product (with its starting stock)" aria-label="Add Product"><i class="bi bi-plus-lg"></i></a>
            @endif
        </div>
    </div>

    @if ($isOwner)
        <div class="alert alert-light border small py-2">
            <i class="bi bi-info-circle"></i>
            This page is for <strong>receiving deliveries</strong> and <strong>counting</strong> stock of items that already exist.
            New items (with their sizes, colors and starting stock, all in one step) are added with the <strong>Add Product</strong> icon (<i class="bi bi-plus-lg"></i>) above.
        </div>
    @endif

    {{-- ====================== 1. BRAND OVERVIEW ====================== --}}
    @if ($mode === 'brands')
        <div class="btn-group mb-2" role="group" aria-label="Inventory view">
            <a href="{{ route('inventory.index') }}" class="btn btn-success btn-icon" title="By brand" aria-label="By brand"><i class="bi bi-grid"></i></a>
            <a href="{{ route('inventory.index', ['view' => 'all']) }}" class="btn btn-outline-success btn-icon" title="All items" aria-label="All items"><i class="bi bi-list-ul"></i></a>
        </div>

        {{-- The brands are loaded once; search, Status and paging work in the browser (no new request to the server). --}}
        <div data-ctable data-per-page="12" data-compact id="brand-table">
            <form class="card shadow-sm mb-3" data-cf-form onsubmit="return false;">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-end">
                        <div class="col-lg-6 col-md-7">
                            <label class="form-label small mb-0">Search</label>
                            <input type="search" data-cf="q" class="form-control form-control-sm" placeholder="Brand, model, category, size, color...">
                        </div>
                        <div class="col-lg-3 col-md-3 col-8">
                            <label class="form-label small mb-0">Status</label>
                            <select data-cf="status" class="form-select form-select-sm">
                                <option value="">All</option>
                                <option value="ok">In-Stock</option>
                                <option value="low">Low stock</option>
                                <option value="out">Out of stock</option>
                            </select>
                        </div>
                        <div class="col-auto">
                            <button type="button" class="btn btn-outline-secondary btn-icon" data-cf-clear title="Clear filters" aria-label="Clear filters"><i class="bi bi-x-circle"></i></button>
                        </div>
                    </div>
                </div>
            </form>

            <div class="row g-3">
                @forelse ($brands as $b)
                    @php
                        $isNone  = $b->brand_name === '';
                        $statuses = collect([$b->ok_count > 0 ? 'ok' : null, $b->low_count > 0 ? 'low' : null, $b->out_count > 0 ? 'out' : null])->filter()->implode('|');
                    @endphp
                    <div class="col-xl-3 col-lg-4 col-sm-6" data-row
                         data-search="{{ mb_strtolower(($isNone ? 'no brand ' : '') . ($brandWords[$b->brand_name] ?? $b->brand_name)) }}"
                         data-status-any="{{ $statuses }}">
                        <a href="{{ route('inventory.index', ['brand' => $isNone ? $noBrand : $b->brand_name]) }}" data-brand-link class="text-decoration-none text-reset">
                            <div class="card brand-card shadow-sm h-100">
                                <div class="card-body">
                                    <div class="fw-bold fs-5">{{ $isNone ? 'No brand' : $b->brand_name }}</div>
                                    <div class="text-muted small">{{ $b->product_count }} model(s)</div>
                                    <div class="mt-2 fs-4 fw-semibold">{{ number_format($b->units) }} <span class="fs-6 text-muted">units</span></div>
                                    <div class="mt-1">
                                        @if ($b->out_count > 0)<span class="badge bg-danger">{{ $b->out_count }} out of stock</span>@endif
                                        @if ($b->low_count > 0)<span class="badge bg-warning text-dark">{{ $b->low_count }} low</span>@endif
                                        @if ($b->out_count == 0 && $b->low_count == 0)<span class="badge bg-success">All In-Stock</span>@endif
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="card shadow-sm"><div class="card-body text-center text-muted py-4">
                            No products yet.
                            @if ($isOwner) Use <strong>Add Product</strong> above to start tracking stock. @endif
                        </div></div>
                    </div>
                @endforelse
                <div class="col-12 d-none" data-empty>
                    <div class="card shadow-sm"><div class="card-body text-center text-muted py-4">No brand matches these filters.</div></div>
                </div>
            </div>
            <div data-pager></div>
        </div>

        <script>
            // Opening a brand while a Status is chosen carries that Status along, so its item list opens already filtered.
            (function () {
                const root = document.getElementById('brand-table'); if (!root) return;
                const status = root.querySelector('[data-cf="status"]');
                const sync = () => root.querySelectorAll('[data-brand-link]').forEach(a => {
                    const u = new URL(a.href, location.origin);
                    status.value ? u.searchParams.set('status', status.value) : u.searchParams.delete('status');
                    a.href = u.pathname + u.search;
                });
                status.addEventListener('change', sync);
                root.querySelector('[data-cf-clear]').addEventListener('click', sync);
            })();
        </script>

    {{-- ====================== 2. ITEM LIST ====================== --}}
    @else
        {{-- Where am I? --}}
        @if ($all && ! $product)
            <div class="btn-group mb-2" role="group" aria-label="Inventory view">
                <a href="{{ route('inventory.index') }}" class="btn btn-outline-success btn-icon" title="By brand" aria-label="By brand"><i class="bi bi-grid"></i></a>
                <a href="{{ route('inventory.index', ['view' => 'all']) }}" class="btn btn-success btn-icon" title="All items" aria-label="All items"><i class="bi bi-list-ul"></i></a>
            </div>
        @endif
        <nav class="small mb-2 {{ $all && ! $product ? 'd-none' : '' }}">
            <a href="{{ route('inventory.index') }}"><i class="bi bi-grid"></i> All brands</a>
            @if ($brand !== '')
                &rsaquo; <a href="{{ route('inventory.index', ['brand' => $brand]) }}">{{ $brand === $noBrand ? 'No brand' : $brand }}</a>
            @endif
            @if ($product)
                &rsaquo; <strong>{{ $product->name }}</strong>
            @endif
        </nav>

        @php
            $showBrand = $all && ! $product;
            $lc        = fn ($v) => mb_strtolower((string) $v);
        @endphp

        {{-- The list is loaded once; the search, filters and paging below work in the browser (no new request to the server). --}}
        <div data-ctable data-per-page="10">
            <form class="card shadow-sm mb-3" data-cf-form onsubmit="return false;">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-end">
                        <div class="{{ $showBrand ? 'col-lg-2' : 'col-lg-3' }} col-md-6">
                            <label class="form-label small mb-0">Search</label>
                            <input type="search" data-cf="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="Model name, size, color">
                        </div>
                        @if ($showBrand)
                            <div class="col-lg-2 col-md-3 col-6">
                                <label class="form-label small mb-0">Brand</label>
                                <select data-cf="brand" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    @foreach ($brandOptions as $bn)<option value="{{ $lc($bn) }}" @selected($lc($brand) === $lc($bn))>{{ $bn }}</option>@endforeach
                                    @if ($hasNoBrand)<option value="{{ $noBrand }}" @selected($brand === $noBrand)>No brand</option>@endif
                                </select>
                            </div>
                        @endif
                        <div class="col-lg-2 col-md-3 col-6">
                            <label class="form-label small mb-0">Category</label>
                            <select data-cf="category" class="form-select form-select-sm">
                                <option value="">All</option>
                                @foreach ($categories as $c)<option value="{{ $lc($c) }}" @selected($lc($category) === $lc($c))>{{ $c }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-3 col-6">
                            <label class="form-label small mb-0">Size</label>
                            <select data-cf="size" class="form-select form-select-sm">
                                <option value="">All</option>
                                @foreach ($sizes as $c)<option value="{{ $lc($c) }}" @selected($lc($size) === $lc($c))>{{ $c }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-3 col-6">
                            <label class="form-label small mb-0">Color</label>
                            <select data-cf="color" class="form-select form-select-sm">
                                <option value="">All</option>
                                @foreach ($colors as $c)<option value="{{ $lc($c) }}" @selected($lc($color) === $lc($c))>{{ $c }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-3 col-6">
                            <label class="form-label small mb-0">Status</label>
                            <select data-cf="status" class="form-select form-select-sm">
                                <option value="">All</option>
                                <option value="ok"  @selected($status === 'ok')>In-Stock</option>
                                <option value="low" @selected($status === 'low')>Low stock</option>
                                <option value="out" @selected($status === 'out')>Out of stock</option>
                            </select>
                        </div>
                        <div class="col-auto">
                            <button type="button" class="btn btn-outline-secondary btn-icon" data-cf-clear title="Clear filters" aria-label="Clear filters"><i class="bi bi-x-circle"></i></button>
                        </div>
                    </div>
                </div>
            </form>

            <div class="card shadow-sm">
                <div class="table-responsive">
                    <table class="table mb-0 align-middle">
                        <thead>
                            <tr>
                                <th style="width:56px"></th>
                                <th>Model</th>
                                <th style="width:140px">Size</th>
                                <th style="width:140px">Color</th>
                                <th class="text-end" style="width:90px">In stock</th>
                                <th class="text-center" style="width:120px">Status</th>
                                @if ($isOwner)
                                    <th class="text-center" style="width:110px">Action</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($variants as $v)
                                @php $state = $v->isOutOfStock() ? 'out' : ($v->isLowStock() ? 'low' : 'ok'); @endphp
                                <tr class="{{ $state !== 'ok' ? 'table-danger' : '' }}" data-row
                                    data-search="{{ $lc($v->product->name . ' ' . ($v->product->brand?->name ?? '') . ' ' . ($v->product->category?->name ?? '') . ' ' . ($v->size?->name ?? '') . ' ' . ($v->color?->name ?? '')) }}"
                                    data-brand="{{ $v->product->brand ? $lc($v->product->brand->name) : $noBrand }}"
                                    data-category="{{ $lc($v->product->category?->name) }}"
                                    data-size="{{ $lc($v->size?->name) }}"
                                    data-color="{{ $lc($v->color?->name) }}"
                                    data-status="{{ $state }}">
                                    <td><x-product-thumb :product="$v->product" :size="40" /></td>
                                    <td>
                                        {{ $v->product->name }}
                                        <div class="small text-muted">{{ collect([$v->product->brand?->name, $v->product->category?->name])->filter()->implode(' · ') }}</div>
                                    </td>
                                    <td>{{ $v->size?->name ?? '—' }}</td>
                                    <td>{{ $v->color?->name ?? '—' }}</td>
                                    <td class="text-end fw-semibold">{{ $v->quantity }}</td>
                                    <td class="text-center">
                                        @if ($state === 'out')
                                            <span class="badge bg-danger status-badge">Out of stock</span>
                                        @elseif ($state === 'low')
                                            <span class="badge bg-warning text-dark status-badge">Low stock</span>
                                        @else
                                            <span class="badge bg-success status-badge">In-Stock</span>
                                        @endif
                                    </td>
                                    @if ($isOwner)
                                        <td class="text-center">
                                            <div class="d-inline-flex gap-1">
                                                {{-- Opens the Receive / Count pop-up for this item --}}
                                                <button type="button" class="btn btn-success btn-icon" title="Receive / Count stock" aria-label="Receive or count stock"
                                                        data-stock-open
                                                        data-action="{{ route('inventory.stock', $v) }}"
                                                        data-product="{{ $v->product->name }}"
                                                        data-size="{{ $v->size?->name ?? '—' }}"
                                                        data-color="{{ $v->color?->name ?? '—' }}"
                                                        data-current="{{ $v->quantity }}">
                                                    <i class="bi bi-clipboard2-plus"></i>
                                                </button>
                                                <a href="{{ route('inventory.history', ['variant' => $v->id]) }}" class="btn btn-outline-secondary btn-icon" title="Stock history" aria-label="Stock history"><i class="bi bi-clock-history"></i></a>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                            <tr data-empty class="d-none"><td colspan="{{ $isOwner ? 7 : 6 }}" class="text-center text-muted py-4">No items match.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div data-pager></div>
        </div>
    @endif

    {{-- ===== Receive / Count pop-up ===== --}}
    @if ($isOwner && $mode === 'items')
        <div class="modal fade" id="stock-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" class="modal-content" id="stock-form">
                    @csrf
                    <input type="hidden" name="mode" id="stock-mode" value="receive">
                    <div class="modal-header">
                        <h5 class="modal-title">Receive / Count stock</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="border rounded p-2 mb-3 bg-light">
                            <div class="fw-semibold" id="stock-product"></div>
                            <div class="small text-muted"><span id="stock-size"></span> &middot; <span id="stock-color"></span></div>
                            <div class="mt-1">In stock now: <strong class="fs-5" id="stock-current"></strong></div>
                        </div>

                        <div class="btn-group mb-3" role="group" aria-label="What are you doing?">
                            <input type="radio" class="btn-check" name="stock_mode_pick" id="pick-receive" value="receive" checked>
                            <label class="btn btn-outline-success btn-icon" for="pick-receive" title="Receive delivery (units arrived)"><i class="bi bi-box-arrow-in-down"></i></label>
                            <input type="radio" class="btn-check" name="stock_mode_pick" id="pick-count" value="count">
                            <label class="btn btn-outline-success btn-icon" for="pick-count" title="Count stock (physical count)"><i class="bi bi-clipboard-check"></i></label>
                        </div>

                        <label class="form-label" for="stock-qty" id="stock-qty-label">How many units arrived?</label>
                        <input type="number" name="quantity" id="stock-qty" min="0" class="form-control form-control-lg" placeholder="Number" required>
                        <div class="small mt-1" id="stock-hint"></div>

                        <div class="mt-3 d-none" id="stock-note-wrap">
                            <label class="form-label" for="stock-note">Why does the count differ?</label>
                            <input type="text" name="note" id="stock-note" maxlength="255" class="form-control" placeholder="e.g. 2 damaged, 1 sold without recording">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-icon" data-bs-dismiss="modal" title="Cancel" aria-label="Cancel"><i class="bi bi-x-lg"></i></button>
                        <button type="submit" class="btn btn-success btn-icon" id="stock-submit" title="Save" aria-label="Save"><i class="bi bi-check-lg"></i></button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            window.addEventListener('load', () => {
                const modal = new bootstrap.Modal('#stock-modal');
                const $ = id => document.getElementById(id);
                const form = $('stock-form'), qty = $('stock-qty'), hint = $('stock-hint');
                const noteWrap = $('stock-note-wrap'), note = $('stock-note'), modeInput = $('stock-mode');
                let current = 0;

                const mode = () => modeInput.value;

                function refresh() {
                    const n = parseInt(qty.value, 10), counting = mode() === 'count';
                    const mismatch = counting && !isNaN(n) && n !== current;

                    noteWrap.classList.toggle('d-none', !mismatch);
                    note.required = mismatch;
                    $('stock-qty-label').textContent = counting ? 'How many did you physically count?' : 'How many units arrived?';

                    if (counting && !isNaN(n)) {
                        hint.textContent = n === current
                            ? 'Matches the system (' + current + ').'
                            : 'System has ' + current + ', you counted ' + n + ' (' + (n > current ? 'over by ' + (n - current) : 'short by ' + (current - n)) + ').';
                        hint.className = 'small mt-1 ' + (n === current ? 'text-success' : 'text-danger');
                    } else if (!counting && !isNaN(n) && n > 0) {
                        hint.textContent = 'Stock will go from ' + current + ' to ' + (current + n) + '.';
                        hint.className = 'small mt-1 text-muted';
                    } else {
                        hint.textContent = '';
                    }
                }

                document.querySelectorAll('[data-stock-open]').forEach(btn => btn.addEventListener('click', () => {
                    current = parseInt(btn.dataset.current, 10);
                    form.action = btn.dataset.action;
                    $('stock-product').textContent = btn.dataset.product;
                    $('stock-size').textContent = 'Size: ' + btn.dataset.size;
                    $('stock-color').textContent = 'Color: ' + btn.dataset.color;
                    $('stock-current').textContent = current;
                    $('pick-receive').checked = true;
                    modeInput.value = 'receive';
                    qty.value = ''; note.value = '';
                    refresh();
                    modal.show();
                }));

                document.querySelectorAll('input[name="stock_mode_pick"]').forEach(r => r.addEventListener('change', () => { modeInput.value = r.value; refresh(); qty.focus(); }));
                qty.addEventListener('input', refresh);
                $('stock-modal').addEventListener('shown.bs.modal', () => qty.focus());

                form.addEventListener('submit', e => {
                    const n = parseInt(qty.value, 10);
                    if (isNaN(n)) { e.preventDefault(); return; }
                    if (mode() === 'receive' && !(n >= 1)) { e.preventDefault(); alert('Enter how many units were received (at least 1).'); return; }
                    if (mode() === 'count' && n !== current &&
                        !confirm('The system shows ' + current + ' but you counted ' + n + '.\nSave this count? The difference will be recorded in Stock History.')) {
                        e.preventDefault();
                        return;
                    }
                    $('stock-submit').disabled = true;   // no double submit
                });
            });
        </script>
    @endif
@endsection
