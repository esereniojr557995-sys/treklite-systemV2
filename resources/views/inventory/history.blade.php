@extends('layouts.app')

@section('title', 'Stock History')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h3 class="mb-0">Stock History</h3>
        <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary btn-icon" title="Back to Inventory" aria-label="Back to Inventory"><i class="bi bi-arrow-left"></i></a>
    </div>

    @if ($variant)
        <div class="alert alert-light border py-2 small">
            Showing only <strong>{{ $variant->product->name }}</strong> ({{ $variant->label }}) &middot; now in stock: <strong>{{ $variant->quantity }}</strong>
            &middot; <a href="{{ route('inventory.history') }}">show everything</a>
        </div>
    @elseif ($product)
        <div class="alert alert-light border py-2 small">
            Showing only <strong>{{ $product->name }}</strong> &middot; <a href="{{ route('inventory.history') }}">show everything</a>
        </div>
    @endif

    {{-- The latest movements are loaded once; the type filter, search and paging all work in the browser. --}}
    <div data-ctable data-per-page="10">
    <form class="card shadow-sm mb-3" data-cf-form onsubmit="return false;">
        <div class="card-body py-2 d-flex flex-wrap gap-3 align-items-end">
            <div>
                <label class="form-label small mb-0">Search</label>
                <input type="search" data-cf="q" class="form-control form-control-sm" placeholder="Item, user or note">
            </div>
            <div>
                <label class="form-label small mb-0">Type</label>
                <select data-cf="type" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="receive" @selected($type === 'receive')>Received (deliveries)</option>
                    <option value="count"   @selected($type === 'count')>Counted</option>
                    <option value="sale"    @selected($type === 'sale')>Sold</option>
                    <option value="void"    @selected($type === 'void')>Sale voided</option>
                </select>
            </div>
            <div class="form-check mb-1">
                <input class="form-check-input" type="checkbox" data-cf="issue" id="issues" @checked($issues)>
                <label class="form-check-label small" for="issues">Only counts that did not match</label>
            </div>
            <div>
                <button type="button" class="btn btn-outline-secondary btn-icon" data-cf-clear title="Clear filters" aria-label="Clear filters"><i class="bi bi-x-circle"></i></button>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Item</th>
                        <th>What happened</th>
                        <th class="text-end">Before</th>
                        <th class="text-end">Change</th>
                        <th class="text-end">After</th>
                        <th>By</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($movements as $m)
                        @php $mismatch = $m->isMismatch(); @endphp
                        <tr class="{{ $mismatch ? 'table-danger' : '' }}" data-row
                            data-search="{{ mb_strtolower($m->variant->product->name . ' ' . $m->variant->label . ' ' . ($m->user->name ?? '') . ' ' . $m->note) }}"
                            data-type="{{ $m->type }}"
                            data-issue="{{ $mismatch ? '1' : '0' }}">
                            <td class="small text-nowrap">{{ $m->created_at->format('M d, Y h:i A') }}</td>
                            <td>
                                {{ $m->variant->product->name }}
                                <div class="small text-muted">{{ $m->variant->label }}</div>
                            </td>
                            <td>
                                @if ($m->type === 'count')
                                    <span class="badge {{ $mismatch ? 'bg-danger' : 'bg-secondary' }}">Counted</span>
                                    <div class="small {{ $mismatch ? 'text-danger fw-semibold' : 'text-muted' }}">
                                        @if ($mismatch)
                                            System {{ $m->expected }} &rarr; counted {{ $m->quantity_after }}
                                            ({{ $m->quantity_change > 0 ? 'over by ' . $m->quantity_change : 'short by ' . abs($m->quantity_change) }})
                                        @else
                                            Matched the system
                                        @endif
                                    </div>
                                @elseif ($m->type === 'receive')
                                    <span class="badge bg-success">Received</span>
                                    <div class="small text-muted">{{ $m->quantity_change }} unit(s) delivered</div>
                                @elseif ($m->type === 'void')
                                    <span class="badge bg-warning text-dark">Sale voided</span>
                                    <div class="small text-muted">{{ abs($m->quantity_change) }} unit(s) returned to stock</div>
                                @else
                                    <span class="badge bg-primary">Sold</span>
                                @endif
                            </td>
                            <td class="text-end">{{ $m->expected }}</td>
                            <td class="text-end fw-semibold {{ $m->quantity_change > 0 ? 'text-success' : ($m->quantity_change < 0 ? 'text-danger' : 'text-muted') }}">
                                {{ $m->quantity_change > 0 ? '+' : '' }}{{ $m->quantity_change }}
                            </td>
                            <td class="text-end">{{ $m->quantity_after }}</td>
                            <td class="small">{{ $m->user->name ?? '—' }}</td>
                            <td class="small text-muted">{{ $m->note }}</td>
                        </tr>
                    @endforeach
                    <tr data-empty class="d-none"><td colspan="8" class="text-center text-muted py-4">No stock movements found.</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div data-pager></div>
    </div>
    @if ($movements->count() >= 2000)
        <div class="small text-muted mt-2">Showing the latest 2,000 movements. Open an item's history (<i class="bi bi-clock-history"></i> in Inventory) to see everything for that item.</div>
    @endif
@endsection
