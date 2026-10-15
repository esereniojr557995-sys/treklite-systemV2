@extends('layouts.app')

@section('title', 'Products')

@section('content')
    @php $noBrand = \App\Http\Controllers\InventoryController::NO_BRAND; @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Products</h3>
        <div class="d-flex gap-2">
            <a href="{{ route('options.index') }}" class="btn btn-outline-secondary btn-icon" title="Manage Options (brands, categories, sizes, colors)" aria-label="Manage Options"><i class="bi bi-list-check"></i></a>
            <a href="{{ route('products.create') }}" class="btn btn-success btn-icon" title="Add Product" aria-label="Add Product"><i class="bi bi-plus-lg"></i></a>
        </div>
    </div>

    <div class="alert alert-light border small py-2">
        <i class="bi bi-info-circle"></i> This page is for <strong>creating and editing products</strong> (name, brand, category, price, sizes, colors and the starting stock).
        Hover over an icon to see what it does. To receive deliveries or count stock later, use <strong>Manage Inventory</strong> (<i class="bi bi-box-seam"></i>) on the product.
    </div>

    {{-- Everything below is filtered in the browser: the list is loaded once and no filter asks the server again. --}}
    <div data-ctable data-per-page="10">
        <form class="card shadow-sm mb-3" data-cf-form onsubmit="return false;">
            <div class="card-body py-2">
                <div class="row g-2 align-items-end">
                    <div class="col-lg-4 col-md-6">
                        <label class="form-label small mb-0">Search</label>
                        <input type="search" data-cf="q" class="form-control form-control-sm" placeholder="Model, brand or category">
                    </div>
                    <div class="col-lg-3 col-md-3 col-6">
                        <label class="form-label small mb-0">Brand</label>
                        <select data-cf="brand" class="form-select form-select-sm">
                            <option value="">All brands</option>
                            @foreach ($brands as $b)<option value="{{ mb_strtolower($b) }}">{{ $b }}</option>@endforeach
                            <option value="{{ $noBrand }}">No brand</option>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-3 col-6">
                        <label class="form-label small mb-0">Category</label>
                        <select data-cf="category" class="form-select form-select-sm">
                            <option value="">All categories</option>
                            @foreach ($categories as $c)<option value="{{ mb_strtolower($c) }}">{{ $c }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-3 col-6">
                        <label class="form-label small mb-0">Show</label>
                        <select data-cf="status" class="form-select form-select-sm">
                            <option value="active" selected>Active</option>
                            <option value="archived">Archived</option>
                            <option value="">All</option>
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
                            <th style="width:64px"></th>
                            <th>Model</th>
                            <th>Brand</th>
                            <th>Category</th>
                            <th class="text-end">Price</th>
                            <th class="text-center">Variants</th>
                            <th class="text-center" style="width:200px">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($products as $p)
                            <tr data-row
                                data-search="{{ mb_strtolower($p->name . ' ' . ($p->brand?->name ?? '') . ' ' . ($p->category?->name ?? '')) }}"
                                data-brand="{{ $p->brand ? mb_strtolower($p->brand->name) : $noBrand }}"
                                data-category="{{ mb_strtolower($p->category?->name ?? '') }}"
                                data-status="{{ $p->is_active ? 'active' : 'archived' }}">
                                <td><x-product-thumb :product="$p" :size="48" /></td>
                                <td class="fw-semibold">
                                    {{ $p->name }}
                                    @unless ($p->is_active) <span class="badge text-bg-warning">Archived</span> @endunless
                                </td>
                                <td>
                                    @if ($p->brand)<span class="badge text-bg-dark">{{ $p->brand->name }}</span>@else<span class="badge text-bg-light border text-muted">No brand</span>@endif
                                </td>
                                <td>
                                    @if ($p->category)<span class="badge text-bg-secondary">{{ $p->category->name }}</span>@else<span class="text-muted">&mdash;</span>@endif
                                </td>
                                <td class="text-end">₱{{ number_format($p->price, 2) }}</td>
                                <td class="text-center">{{ $p->variants->count() }}</td>
                                <td class="text-center">
                                    <div class="d-inline-flex gap-1 justify-content-center">
                                        @if ($p->is_active)
                                            <a href="{{ route('inventory.index', ['product' => $p->id]) }}" class="btn btn-outline-success btn-icon" title="Manage Inventory" aria-label="Manage Inventory"><i class="bi bi-box-seam"></i></a>
                                            <a href="{{ route('products.edit', $p) }}" class="btn btn-outline-secondary btn-icon" title="Edit" aria-label="Edit"><i class="bi bi-pencil-square"></i></a>
                                            <form method="POST" action="{{ route('products.archive', $p) }}" class="d-inline"
                                                  onsubmit="return confirm('Archive {{ addslashes($p->name) }}? It will no longer appear in New Sale or Inventory. Past sales are not affected.');">
                                                @csrf
                                                <button class="btn btn-outline-danger btn-icon" title="Archive" aria-label="Archive"><i class="bi bi-archive"></i></button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('products.restore', $p) }}" class="d-inline">
                                                @csrf
                                                <button class="btn btn-outline-success btn-icon" title="Restore" aria-label="Restore"><i class="bi bi-arrow-counterclockwise"></i></button>
                                            </form>
                                        @endif
                                        @if ($deletable->contains($p->id))
                                            <form method="POST" action="{{ route('products.destroy', $p) }}" class="d-inline"
                                                  onsubmit="return confirm('Permanently delete {{ addslashes($p->name) }}? This cannot be undone.');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-danger btn-icon" title="Delete (no sales or stock records yet)" aria-label="Delete"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        <tr data-empty class="d-none"><td colspan="7" class="text-center text-muted py-4">No products match these filters.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div data-pager></div>
    </div>
@endsection
