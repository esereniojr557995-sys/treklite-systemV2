@extends('layouts.app')

@section('title', 'Sales Report')

@section('content')
    <style>
        @page { size: A4 portrait; margin: 10mm; }
        @media print {
            .chart-card { break-inside: avoid; }
            .chart-box { height: 220px !important; }
        }
        .chart-box { position: relative; height: 280px; }
    </style>

    @include('reports.partials.styles')

    @php
        $link = fn (array $over = []) => route('reports.sales', array_filter(array_merge([
            'period' => $period,
            'date'   => $date->toDateString(),
        ], $over), fn ($v) => $v !== null));
        $periodOptions = ['day' => 'Today', 'week' => 'Week', 'month' => 'Month', 'year' => 'Year'];

        // ---------- chart data (built from the data the controller already passes) ----------
        $singleDay = $from->isSameDay($to);
        $byMonthView = $from->diffInDays($to) > 120;
        $timeLabels = [];
        $timeRevenue = [];
        $timeCounts = [];

        if ($singleDay) {
            // Group by hour of the day
            $byHour = $sales->groupBy(fn ($s) => (int) $s->sold_at->format('G'));
            $hours = $byHour->keys()->all();
            $minH = count($hours) ? min(8, min($hours)) : 8;
            $maxH = count($hours) ? max(20, max($hours)) : 20;
            foreach (range($minH, $maxH) as $h) {
                $group = $byHour->get($h, collect());
                $timeLabels[]  = \Carbon\Carbon::createFromTime($h)->format('g A');
                $timeRevenue[] = round((float) $group->sum('total_amount'), 2);
                $timeCounts[]  = $group->count();
            }
        } elseif ($byMonthView) {
            $byMonth = $sales->groupBy(fn ($s) => $s->sold_at->format('Y-m'));
            foreach (\Carbon\CarbonPeriod::create($from->copy()->startOfMonth(), '1 month', $to->copy()->startOfMonth()) as $m) {
                $group = $byMonth->get($m->format('Y-m'), collect());
                $timeLabels[]  = $m->format('M Y');
                $timeRevenue[] = round((float) $group->sum('total_amount'), 2);
                $timeCounts[]  = $group->count();
            }
        } else {
            // Group by day, including days with no sales
            $byDay = $sales->groupBy(fn ($s) => $s->sold_at->format('Y-m-d'));
            $dayRange = \Carbon\CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay());
            foreach ($dayRange as $day) {
                $group = $byDay->get($day->format('Y-m-d'), collect());
                $timeLabels[]  = $day->format('M d');
                $timeRevenue[] = round((float) $group->sum('total_amount'), 2);
                $timeCounts[]  = $group->count();
            }
        }

        $payLabels = [];
        $payTotals = [];
        foreach (\App\Models\Sale::PAYMENT_LABELS as $key => $label) {
            $t = (float) ($byPayment[$key]['total'] ?? 0);
            if ($t > 0) {
                $payLabels[] = $label;
                $payTotals[] = round($t, 2);
            }
        }
    @endphp

    @if (($voidedCount ?? 0) > 0)
        <div class="alert alert-warning py-2 small no-print">
            <i class="bi bi-exclamation-triangle"></i> {{ $voidedCount }} voided sale(s) (₱{{ number_format($voidedTotal, 2) }}) in this period are <strong>not</strong> included below.
        </div>
    @endif

    {{-- Paper heading (hidden on screen) --}}
    <div class="print-header d-none d-print-block">
        <div class="ph-brand">Treklite Outdoor</div>
        <div class="ph-title">Sales Report</div>
        <div class="ph-meta">Printed {{ now()->format('M d, Y h:i A') }}</div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3 d-print-none">
        <h3 class="mb-0">Sales Report</h3>
        @include('reports.partials.actions', ['exportRoute' => 'reports.sales.export'])
    </div>
    
    <ul class="nav nav-tabs mb-3 no-print">
    <li class="nav-item"><a class="nav-link active" href="{{ route('reports.sales') }}">Sales Report</a></li>
    <li class="nav-item"><a class="nav-link" href="{{ route('reports.inventory') }}">Inventory Report</a></li>
    </ul>

    <div class="filter-bar mb-3 no-print">
        <div class="filter-group">
            <span class="filter-label">Period</span>
            <div class="btn-group" role="group" aria-label="Report period">
                @foreach ($periodOptions as $key => $label)
                    <a href="{{ $link(['period' => $key, 'from' => null, 'to' => null] + ($key === 'day' ? ['date' => now()->toDateString()] : [])) }}"
                       class="btn btn-sm {{ $period === $key ? 'btn-success' : 'btn-outline-success' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>

        <div class="filter-divider"></div>

        <form method="GET" class="filter-group mb-0">
            <input type="hidden" name="period" value="custom">
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
    </div>

    <div class="small text-muted mb-2">{{ $from->format('M d, Y') }} &ndash; {{ $to->format('M d, Y') }}</div>

    <div class="row g-3 mb-3 print-row">
        <div class="col-md-4">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Total revenue</div>
                <div class="fs-4 fw-bold">₱{{ number_format($totalRevenue, 2) }}</div>
                <div class="text-muted small">{{ $sales->count() }} transaction(s)</div>
            </div></div>
        </div>
        <div class="col-md-8">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted small mb-1">By payment method</div>
                @foreach (\App\Models\Sale::PAYMENT_LABELS as $key => $label)
                    {{-- Old methods (PayMaya / Card) only show when they have sales in this period --}}
                    @continue(! isset(\App\Models\Sale::PAYMENT_METHODS[$key]) && ($byPayment[$key]['total'] ?? 0) <= 0)
                    <div class="d-flex justify-content-between small">
                        <span>{{ $label }} <span class="text-muted">({{ $byPayment[$key]['count'] ?? 0 }})</span></span>
                        <span class="fw-semibold">₱{{ number_format($byPayment[$key]['total'] ?? 0, 2) }}</span>
                    </div>
                @endforeach
            </div></div>
        </div>
    </div>

    {{-- ============ Charts ============ --}}
    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <div class="card shadow-sm h-100 chart-card">
                <div class="card-header fw-bold">
                    <i class="bi bi-bar-chart-line"></i>
                    Revenue {{ $singleDay ? 'by hour' : ($byMonthView ? 'by month' : 'by day') }}
                </div>
                <div class="card-body">
                    @if ($sales->isEmpty())
                        <div class="text-center text-muted py-5">No sales in this range.</div>
                    @else
                        <div class="chart-box"><canvas id="timeChart"></canvas></div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm h-100 chart-card">
                <div class="card-header fw-bold"><i class="bi bi-pie-chart"></i> Sales by payment method</div>
                <div class="card-body">
                    @if (empty($payTotals))
                        <div class="text-center text-muted py-5">No sales in this range.</div>
                    @else
                        <div class="chart-box"><canvas id="payChart"></canvas></div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Shown 10 per page on screen; the printed report always has every row. --}}
    <div data-ctable data-per-page="10">
    <div class="card shadow-sm">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Processed By</th>
                    <th>Payment</th>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sales as $sale)
                    <tr data-row>
                        <td>{{ $sale->invoice_number }}</td>
                        <td>{{ $sale->sold_at->format('M d, Y h:i A') }}</td>
                        <td>{{ $sale->customer_name }}</td>
                        <td>{{ $sale->user->name }}</td>
                        <td>{{ $sale->payment_label }}</td>
                        <td class="text-end">₱{{ number_format($sale->total_amount, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No sales in this range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div data-pager class="no-print"></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const peso = n => '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            const timeLabels  = @json($timeLabels);
            const timeRevenue = @json($timeRevenue);
            const timeCounts  = @json($timeCounts);
            const payLabels   = @json($payLabels);
            const payTotals   = @json($payTotals);

            const timeEl = document.getElementById('timeChart');
            if (timeEl) {
                new Chart(timeEl, {
                    type: 'bar',
                    data: {
                        labels: timeLabels,
                        datasets: [{
                            label: 'Revenue',
                            data: timeRevenue,
                            backgroundColor: '#1e5b31',
                            borderRadius: 4,
                            maxBarThickness: 48,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: ctx => peso(ctx.parsed.y),
                                    afterLabel: ctx => timeCounts[ctx.dataIndex] + ' transaction(s)',
                                },
                            },
                        },
                        scales: {
                            y: { beginAtZero: true, ticks: { callback: v => '₱' + Number(v).toLocaleString('en-PH') } },
                            x: { grid: { display: false } },
                        },
                    },
                });
            }

            const payEl = document.getElementById('payChart');
            if (payEl) {
                new Chart(payEl, {
                    type: 'doughnut',
                    data: {
                        labels: payLabels,
                        datasets: [{
                            data: payTotals,
                            backgroundColor: ['#1e5b31', '#0d6efd', '#6f42c1', '#fd7e14', '#20c997'],
                            borderWidth: 2,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom' },
                            tooltip: { callbacks: { label: ctx => ctx.label + ': ' + peso(ctx.parsed) } },
                        },
                    },
                });
            }
        });
    </script>
@endsection