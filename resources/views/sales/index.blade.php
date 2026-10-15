@extends('layouts.app')

@section('title', 'Sales Records')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Sales Records</h3>
        <div class="d-flex gap-2">
            @if (auth()->user()->hasFullAccess())
                {{-- Downloads every sale that matches the filters below (not just this page). The links are updated by the script at the bottom. --}}
                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Export (PDF / Excel)" aria-label="Export">
                        <i class="bi bi-download"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" data-export="pdf" href="{{ route('sales.export', ['format' => 'pdf']) }}"><i class="bi bi-file-earmark-pdf text-danger"></i> PDF</a></li>
                        <li><a class="dropdown-item" data-export="xlsx" href="{{ route('sales.export', ['format' => 'xlsx']) }}"><i class="bi bi-file-earmark-excel text-success"></i> Excel (.xlsx)</a></li>
                    </ul>
                </div>
            @endif
            <a href="{{ route('sales.create') }}" class="btn btn-success btn-icon" title="New Sale" aria-label="New Sale"><i class="bi bi-cart-plus"></i></a>
        </div>
    </div>

    {{-- All sales are loaded once. Search, dates, cashier, payment, the totals and paging all work in the browser. --}}
    <div data-ctable data-per-page="10" id="sales-table">
        <form class="card shadow-sm mb-3" data-cf-form onsubmit="return false;">
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label small mb-0">Search</label>
                        <input type="search" data-cf="q" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="Invoice no., customer, contact">
                    </div>
                    <div class="col-lg-2 col-md-3 col-6">
                        <label class="form-label small mb-0">From</label>
                        <input type="date" data-cf="from" name="from" value="{{ $from }}" class="form-control form-control-sm">
                    </div>
                    <div class="col-lg-2 col-md-3 col-6">
                        <label class="form-label small mb-0">To</label>
                        <input type="date" data-cf="to" name="to" value="{{ $to }}" class="form-control form-control-sm">
                    </div>
                    @if ($isOwner)
                        <div class="col-lg-2 col-md-4 col-6">
                            <label class="form-label small mb-0">Cashier</label>
                            <select data-cf="cashier" name="cashier" class="form-select form-select-sm">
                                <option value="">All cashiers</option>
                                @foreach ($cashiers as $c)
                                    <option value="{{ $c->id }}" @selected($cashier === $c->id)>{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="col-lg-2 col-md-4 col-6">
                        <label class="form-label small mb-0">Payment</label>
                        <select data-cf="payment" name="payment" class="form-select form-select-sm">
                            <option value="">All payments</option>
                            @foreach (\App\Models\Sale::PAYMENT_METHODS as $key => $label)
                                <option value="{{ $key }}" @selected($payment === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto d-flex gap-1">
                        <button type="button" class="btn btn-outline-secondary btn-icon" data-range="today" title="Today" aria-label="Today"><i class="bi bi-calendar-day"></i></button>
                        <button type="button" class="btn btn-outline-secondary btn-icon" data-range="week" title="This week" aria-label="This week"><i class="bi bi-calendar-week"></i></button>
                        <button type="button" class="btn btn-outline-secondary btn-icon" data-range="month" title="This month" aria-label="This month"><i class="bi bi-calendar-month"></i></button>
                        <button type="button" class="btn btn-outline-secondary btn-icon" data-cf-clear title="Clear filters" aria-label="Clear filters"><i class="bi bi-x-circle"></i></button>
                    </div>
                </div>
            </div>
        </form>

        <div class="small text-muted mb-2">
            <span id="sum-count">0</span> sale(s) &middot; Total <strong class="text-dark" id="sum-total">₱0.00</strong>
            <span id="sum-void-wrap" class="d-none">&middot; <span class="text-danger"><span id="sum-void">0</span> voided (not counted)</span></span>
        </div>

        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Processed By</th>
                            <th>Payment</th>
                            <th class="text-end">Total</th>
                            <th class="text-center" style="width:80px">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sales as $sale)
                            @php $voided = $sale->isVoided(); @endphp
                            <tr data-row
                                data-search="{{ mb_strtolower($sale->invoice_number . ' ' . $sale->customer_name . ' ' . $sale->customer_contact) }}"
                                data-date="{{ $sale->sold_at->format('Y-m-d') }}"
                                data-cashier="{{ $sale->user_id }}"
                                data-payment="{{ $sale->payment_method }}"
                                data-amount="{{ $sale->total_amount }}"
                                data-void="{{ $voided ? 1 : 0 }}">
                                <td>
                                    <a href="{{ route('sales.show', $sale) }}" class="{{ $voided ? 'text-decoration-line-through text-muted' : '' }}">{{ $sale->invoice_number }}</a>
                                    @if ($voided) <span class="badge bg-danger">VOIDED</span> @endif
                                </td>
                                <td>{{ $sale->sold_at->format('M d, Y h:i A') }}</td>
                                <td>{{ $sale->customer_name }}</td>
                                <td>{{ $sale->user->name }}</td>
                                <td>
                                    {{ $sale->payment_label }}
                                    @if ($sale->payment_reference)
                                        <div class="small text-muted">Ref: {{ $sale->payment_reference }}</div>
                                    @endif
                                </td>
                                <td class="text-end {{ $voided ? 'text-decoration-line-through text-muted' : '' }}">₱{{ number_format($sale->total_amount, 2) }}</td>
                                <td class="text-center">
                                    <a href="{{ route('sales.show', $sale) }}" class="btn btn-outline-secondary btn-icon" title="View invoice" aria-label="View invoice"><i class="bi bi-eye"></i></a>
                                </td>
                            </tr>
                        @endforeach
                        <tr data-empty class="d-none"><td colspan="7" class="text-center text-muted py-4">{{ $sales->isEmpty() ? 'No sales recorded yet.' : 'No sales match these filters.' }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div data-pager></div>
    </div>

    @if ($sales->count() >= 5000)
        <div class="small text-muted mt-2">Showing the latest 5,000 sales. Use Export for older records.</div>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const root = document.getElementById('sales-table');
            const peso = n => '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const $ = id => document.getElementById(id);

            // Totals follow the filters, using only the rows already in the page (voided sales are never counted).
            root.addEventListener('cf:filtered', e => {
                const rows = e.detail.matched;
                const valid = rows.filter(r => r.dataset.void !== '1');
                $('sum-count').textContent = valid.length.toLocaleString();
                $('sum-total').textContent = peso(valid.reduce((s, r) => s + parseFloat(r.dataset.amount || 0), 0));
                const voided = rows.length - valid.length;
                $('sum-void').textContent = voided;
                $('sum-void-wrap').classList.toggle('d-none', voided === 0);

                // The export downloads exactly what the filters show.
                const params = new URLSearchParams();
                root.querySelectorAll('[data-cf]').forEach(c => { if (c.value.trim() !== '') params.set(c.name, c.value.trim()); });
                document.querySelectorAll('[data-export]').forEach(a => {
                    const p = new URLSearchParams(params);
                    p.set('format', a.dataset.export);
                    a.href = @json(route('sales.export')) + '?' + p.toString();
                });
            });

            // Today / This week / This month fill in the dates
            const iso = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
            root.querySelectorAll('[data-range]').forEach(b => b.addEventListener('click', () => {
                const now = new Date(), from = new Date(now);
                if (b.dataset.range === 'week') from.setDate(now.getDate() - ((now.getDay() + 6) % 7));   // week starts on Monday
                if (b.dataset.range === 'month') from.setDate(1);
                root.querySelector('[data-cf="from"]').value = iso(from);
                root.querySelector('[data-cf="to"]').value = iso(now);
                root.querySelector('[data-cf="to"]').dispatchEvent(new Event('change'));
            }));
        });
    </script>
@endsection
