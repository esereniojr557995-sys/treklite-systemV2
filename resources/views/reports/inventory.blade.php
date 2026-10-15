@extends('layouts.app')

@section('title', 'Inventory Report')

@section('content')
    <style>
        @page { size: A4 landscape; margin: 10mm; }
    </style>

    @include('reports.partials.styles')

    @php
        // Every link on this page keeps the chosen period, date and the low-stock switch.
        $link = fn (array $over = []) => route('reports.inventory', array_filter(array_merge([
            'period'    => $period,
            'date'      => $date->toDateString(),
            'from'      => $period === 'custom' ? $from->toDateString() : null,
            'to'        => $period === 'custom' ? $to->toDateString() : null,
            'attention' => $onlyAttention ? 1 : null,
        ], $over), fn ($v) => $v !== null));
        $periods = ['day' => 'Today', 'week' => 'Week', 'month' => 'Month', 'year' => 'Year'];
    @endphp

    {{-- Paper heading (hidden on screen) --}}
    <div class="print-header d-none d-print-block">
        <div class="ph-brand">Treklite Outdoor</div>
        <div class="ph-title">Inventory Report</div>
        <div class="ph-meta">Printed {{ now()->format('M d, Y h:i A') }}</div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3 d-print-none">
        <h3 class="mb-0">Inventory Report</h3>
        @include('reports.partials.actions', ['exportRoute' => 'reports.inventory.export'])
    </div>

    {{-- Report tabs --}}
    <ul class="nav nav-tabs mb-3 no-print">
        <li class="nav-item"><a class="nav-link" href="{{ route('reports.sales') }}">Sales Report</a></li>
        <li class="nav-item"><a class="nav-link active" href="{{ route('reports.inventory') }}">Inventory Report</a></li>
    </ul>

    {{-- Filter: Today / Week / Month / Year, or a custom range --}}
    <div class="filter-bar mb-3 no-print">
        <div class="filter-group">
            <span class="filter-label">Period</span>
            <div class="btn-group" role="group" aria-label="Report period">
                @foreach ($periods as $key => $label)
                    <a href="{{ $link(['period' => $key, 'from' => null, 'to' => null] + ($key === 'day' ? ['date' => now()->toDateString()] : [])) }}"
                       class="btn btn-sm {{ $period === $key ? 'btn-success' : 'btn-outline-success' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>

        <div class="filter-divider"></div>

        <form method="GET" class="filter-group mb-0">
            <input type="hidden" name="period" value="custom">
            @if ($onlyAttention)<input type="hidden" name="attention" value="1">@endif
            <span class="filter-label">Custom</span>
            <div class="range-field">
                <label for="from">From</label>
                <input type="date" id="from" name="from" class="form-control form-control-sm" value="{{ $from->format('Y-m-d') }}" max="{{ now()->toDateString() }}">
            </div>
            <span class="range-sep">&rarr;</span>
            <div class="range-field">
                <label for="to">To</label>
                <input type="date" id="to" name="to" class="form-control form-control-sm" value="{{ $to->format('Y-m-d') }}" max="{{ now()->toDateString() }}">
            </div>
            <button class="btn btn-icon {{ $period === 'custom' ? 'btn-success' : 'btn-outline-success' }}" title="Apply dates" aria-label="Apply dates"><i class="bi bi-check-lg"></i></button>
        </form>

        <div class="filter-divider"></div>

        <form method="GET" class="form-check form-switch mb-0">
            <input type="hidden" name="period" value="{{ $period }}">
            @if ($period === 'custom')
                <input type="hidden" name="from" value="{{ $from->toDateString() }}">
                <input type="hidden" name="to" value="{{ $to->toDateString() }}">
            @else
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
            @endif
            <input class="form-check-input" type="checkbox" role="switch" name="attention" value="1" id="attention"
                   @checked($onlyAttention) onchange="this.form.submit()">
            <label class="form-check-label small" for="attention">Low / out-of-stock only</label>
        </form>
    </div>

    <div class="small text-muted mb-2">
        Stock as of {{ now()->format('M d, Y h:i A') }}
        &middot; Received / Sold / Adjusted for <strong>{{ $periodLabel }}</strong>
    </div>

    <div class="row g-3 mb-3 print-row">
        <div class="col-6 col-md-3"><div class="card shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Units on hand</div><div class="fs-4 fw-bold">{{ number_format($totalUnits) }}</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Stock value</div><div class="fs-4 fw-bold">₱{{ number_format($totalValue, 2) }}</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card stat-soft stat-warn h-100"><div class="card-body">
            <div class="stat-label"><i class="bi bi-exclamation-circle"></i> Low stock</div><div class="stat-value">{{ $lowCount }}</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card stat-soft stat-danger h-100"><div class="card-body">
            <div class="stat-label"><i class="bi bi-x-circle"></i> Out of stock</div><div class="stat-value">{{ $outCount }}</div></div></div></div>
    </div>

    {{-- Shown 10 per page on screen; the printed report always has every row. --}}
    <div data-ctable data-per-page="10">
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Size / Color</th>
                        <th class="text-end">Quantity</th>
                        <th class="text-end" title="Delivered in {{ $periodLabel }}">Received</th>
                        <th class="text-end" title="Sold in {{ $periodLabel }} (voided sales taken out)">Sold</th>
                        <th class="text-end" title="Changed by a physical count in {{ $periodLabel }}">Adjusted</th>
                        <th class="text-end">Low at</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Unit price</th>
                        <th class="text-end">Stock value</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($variants as $v)
                        <tr data-row>
                            <td>{{ $v->product->name }}</td>
                            <td>{{ $v->label }}</td>
                            <td class="text-end">{{ $v->quantity }}</td>
                            <td class="text-end {{ $v->period_received ? '' : 'text-muted' }}">{{ $v->period_received ?: '–' }}</td>
                            <td class="text-end {{ $v->period_sold ? '' : 'text-muted' }}">{{ $v->period_sold ?: '–' }}</td>
                            <td class="text-end {{ $v->period_adjusted ? '' : 'text-muted' }}">{{ $v->period_adjusted ? ($v->period_adjusted > 0 ? '+' : '') . $v->period_adjusted : '–' }}</td>
                            <td class="text-end text-muted">{{ $v->low_stock_threshold }}</td>
                            <td class="text-center">
                                @if ($v->isOutOfStock())
                                    <span class="badge badge-soft badge-soft-danger">Out of stock</span>
                                @elseif ($v->isLowStock())
                                    <span class="badge badge-soft badge-soft-warn">Low stock</span>
                                @else
                                    <span class="badge badge-soft badge-soft-ok">In-Stock</span>
                                @endif
                            </td>
                            <td class="text-end">₱{{ number_format($v->selling_price, 2) }}</td>
                            <td class="text-end">₱{{ number_format($v->quantity * $v->selling_price, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center text-muted py-4">Nothing to show.</td></tr>
                    @endforelse
                </tbody>
                @if ($variants->isNotEmpty())
                    <tfoot>
                        <tr class="fw-bold">
                            <td colspan="2" class="text-end">Total</td>
                            <td class="text-end">{{ number_format($totalUnits) }}</td>
                            <td class="text-end">{{ number_format($periodReceived) }}</td>
                            <td class="text-end">{{ number_format($periodSold) }}</td>
                            <td class="text-end">{{ ($periodAdjusted > 0 ? '+' : '') . number_format($periodAdjusted) }}</td>
                            <td colspan="3"></td>
                            <td class="text-end">₱{{ number_format($totalValue, 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
    <div data-pager class="no-print"></div>
    </div>
@endsection
