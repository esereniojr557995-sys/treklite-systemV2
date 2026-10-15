@extends('layouts.app')

@section('title', 'Invoice ' . $sale->invoice_number)

@section('content')
    <style>
        /* ---- The receipt: one fixed layout, so every invoice looks the same on screen and on paper ---- */
        .invoice { max-width: 760px; font-size: .92rem; }
        .rc-meta { display: grid; grid-template-columns: 1fr 1fr; gap: .15rem 1.5rem; font-size: .85rem; margin-bottom: 1rem; }
        .rc-meta .rc-right { text-align: right; }
        .rc-items { table-layout: fixed; width: 100%; margin-bottom: 0; }
        .rc-items th, .rc-items td { padding: .4rem .5rem; vertical-align: top; word-wrap: break-word; }
        .rc-items .c-item   { width: 36%; }
        .rc-items .c-opt    { width: 24%; }
        .rc-items .c-qty    { width: 8%;  text-align: right; }
        .rc-items .c-price  { width: 16%; text-align: right; }
        .rc-items .c-amount { width: 16%; text-align: right; }
        .rc-pay { width: 100%; max-width: 330px; margin-left: auto; font-size: .85rem; }
        .rc-pay td { padding: .12rem 0; }
        .rc-pay td:last-child { text-align: right; font-weight: 600; }

        @media print {
            /* A margin of 0 on the page also removes the browser's own date / title / web address lines; the padding below gives the paper margin. */
            @page { size: A4 portrait; margin: 0; }
            .sidebar, .topbar, .no-print, .alert, .sidebar-backdrop { display: none !important; }
            .main-wrapper { margin-left: 0 !important; }
            .main-wrapper > .container-fluid { padding: 0 !important; }
            body { background: #fff !important; }
            .invoice { box-shadow: none !important; border: 0 !important; max-width: 170mm !important; margin: 15mm auto !important; }
            .invoice .card-body { padding: 0 !important; }
        }
    </style>

    <div class="d-flex justify-content-between mb-3 no-print">
        <a href="{{ route('sales.create') }}" class="btn btn-success btn-icon" title="New Sale" aria-label="New Sale"><i class="bi bi-arrow-left"></i></a>
        <div class="d-flex gap-2">
            <a href="{{ route('sales.index') }}" class="btn btn-outline-secondary btn-icon" title="Sales Records" aria-label="Sales Records"><i class="bi bi-receipt"></i></a>
            <button type="button" onclick="window.print()" class="btn btn-outline-dark btn-icon" title="Print" aria-label="Print"><i class="bi bi-printer"></i></button>
            @if (auth()->user()->hasFullAccess() && ! $sale->isVoided())
                <button type="button" class="btn btn-outline-danger btn-icon" data-bs-toggle="modal" data-bs-target="#void-modal" title="Void Sale" aria-label="Void Sale"><i class="bi bi-x-octagon"></i></button>
            @endif
        </div>
    </div>

    @if ($errors->has('reason'))
        <div class="alert alert-danger no-print">{{ $errors->first('reason') }}</div>
    @endif

    @if ($sale->isVoided())
        <div class="alert alert-danger mx-auto" style="max-width: 760px;">
            <strong><i class="bi bi-x-octagon"></i> THIS INVOICE WAS VOIDED</strong> and is not counted in sales totals.
            <div class="small mt-1">
                {{ $sale->voided_at->format('M d, Y h:i A') }} by {{ $sale->voidedBy?->name ?? 'unknown' }}.
                Reason: {{ $sale->void_reason }}
            </div>
        </div>
    @endif

    <div class="card shadow-sm invoice mx-auto {{ $sale->isVoided() ? 'border-danger' : '' }}">
        <div class="card-body p-4">
            <div class="text-center mb-3">
                <h4 class="mb-0">Treklite Outdoor</h4>
                <div class="small text-muted">Door 1, Amarelio Building, Green Meadow Subdivision</div>
                <div class="fw-bold mt-2 text-uppercase">Sales Invoice @if ($sale->isVoided()) <span class="badge bg-danger">VOIDED</span> @endif</div>
            </div>

            <div class="rc-meta">
                <div><span class="text-muted">Sold to:</span> <strong>{{ $sale->customer_name }}</strong></div>
                <div class="rc-right"><span class="text-muted">Invoice No.:</span> <strong>{{ $sale->invoice_number }}</strong></div>
                <div><span class="text-muted">Address:</span> {{ $sale->customer_address ?: '—' }}</div>
                <div class="rc-right"><span class="text-muted">Date:</span> {{ $sale->sold_at->format('M d, Y h:i A') }}</div>
                <div><span class="text-muted">Contact:</span> {{ $sale->customer_contact ?: '—' }}</div>
                <div class="rc-right"><span class="text-muted">Cashier:</span> {{ $sale->user->name }}</div>
            </div>

            <table class="table table-sm rc-items">
                <thead>
                    <tr>
                        <th class="c-item">Item</th>
                        <th class="c-opt">Size / Color</th>
                        <th class="c-qty">Qty</th>
                        <th class="c-price">Unit Price</th>
                        <th class="c-amount">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sale->items as $item)
                        <tr>
                            <td class="c-item">{{ $item->variant->product->name }}</td>
                            <td class="c-opt">{{ $item->variant->label }}</td>
                            <td class="c-qty">{{ $item->quantity }}</td>
                            <td class="c-price">₱{{ number_format($item->unit_price, 2) }}</td>
                            <td class="c-amount">₱{{ number_format($item->subtotal, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="4" class="text-end">Total</th>
                        <th class="c-amount">₱{{ number_format($sale->total_amount, 2) }}</th>
                    </tr>
                </tfoot>
            </table>

            <table class="rc-pay mt-3">
                <tr><td class="text-muted fw-normal">Payment method</td><td>{{ $sale->payment_label }}</td></tr>
                <tr><td class="text-muted fw-normal">Amount received</td><td>₱{{ number_format($sale->amount_tendered, 2) }}</td></tr>
                <tr><td class="text-muted fw-normal">Change</td><td>₱{{ number_format($sale->change_amount, 2) }}</td></tr>
                @if ($sale->payment_reference)
                    {{-- Only GCash / bank sales have a reference number --}}
                    <tr><td class="text-muted fw-normal">Reference no.</td><td>{{ $sale->payment_reference }}</td></tr>
                @endif
            </table>

            @if ($sale->payment_proof_url)
                <div class="mt-3 no-print">
                    <div class="small text-muted">Payment confirmation</div>
                    <a href="{{ $sale->payment_proof_url }}" target="_blank"><img src="{{ $sale->payment_proof_url }}" alt="Payment proof" style="max-height: 160px;" class="img-thumbnail"></a>
                </div>
            @endif

            <div class="text-center text-muted small mt-4">Thank you for shopping at Treklite Outdoor!</div>
        </div>
    </div>

    @if (auth()->user()->hasFullAccess() && ! $sale->isVoided())
        <div class="modal fade no-print" id="void-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('sales.void', $sale) }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Void {{ $sale->invoice_number }}?</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted mb-2">
                            The sale stays on record as VOIDED, the items go back to stock, and it is no longer counted in sales totals.
                            If the customer already paid, return their money yourself - the system does not handle refunds.
                        </p>
                        <label for="void-reason" class="form-label">Reason <span class="text-danger">*</span></label>
                        <textarea name="reason" id="void-reason" rows="3" maxlength="255" class="form-control" required placeholder="e.g. Customer returned the tent, wrong size encoded">{{ old('reason') }}</textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-icon" data-bs-dismiss="modal" title="Cancel" aria-label="Cancel"><i class="bi bi-x-lg"></i></button>
                        <button class="btn btn-danger btn-icon" title="Void this sale" aria-label="Void this sale"><i class="bi bi-x-octagon"></i></button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection
