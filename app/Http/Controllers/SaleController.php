<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleVoid;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\ImageStore;
use App\Support\TableExport;
use Illuminate\Support\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    /**
     * Sales Records. The sales are loaded ONCE; the search, date range, cashier and payment filters, the totals and the
     * paging all work in the browser, so changing a filter never queries the server again.
     * (The PDF / Excel export still uses the server, because it builds a file.)
     */
    public function index(Request $request)
    {
        $isOwner = auth()->user()->hasFullAccess();

        // The filters in the link (if any) only pre-fill the filter boxes.
        [, $filters] = $this->filteredSales($request, $isOwner);

        $sales = Sale::query()
            ->when(! $isOwner, fn ($w) => $w->where('user_id', auth()->id()))
            ->with('user')
            ->latest('sold_at')
            ->limit(5000)
            ->get();

        $cashiers = $isOwner ? User::orderBy('name')->get(['id', 'name']) : collect();

        return view('sales.index', $filters + compact('sales', 'cashiers', 'isOwner'));
    }

    /**
     * Owner only: the same filtered list as Sales Records (every matching sale, not just the page on screen),
     * downloaded as a PDF (?format=pdf) or an Excel file (?format=xlsx, the default).
     */
    public function export(Request $request)
    {
        [$query, $filters] = $this->filteredSales($request, true);
        $sales  = $query->with('user')->latest('sold_at')->get()->values();
        $active = $sales->filter(fn ($s) => ! $s->isVoided());

        // Describe what the filters were, so the file explains itself.
        $notes = [];
        if ($filters['from'] || $filters['to']) {
            $notes[] = 'Date range: ' . ($filters['from'] ? Carbon::parse($filters['from'])->format('M d, Y') : 'start')
                . ' – ' . ($filters['to'] ? Carbon::parse($filters['to'])->format('M d, Y') : 'today');
        }
        if ($filters['q'] !== '')       { $notes[] = 'Search: ' . $filters['q']; }
        if ($filters['cashier'])        { $notes[] = 'Cashier: ' . (User::find($filters['cashier'])?->name ?? '—'); }
        if ($filters['payment'] !== '') { $notes[] = 'Payment: ' . (Sale::PAYMENT_LABELS[$filters['payment']] ?? $filters['payment']); }
        if (! $notes) { $notes[] = 'All sales (no filters)'; }

        $voidedCount = $sales->count() - $active->count();

        $data = [
            'title'    => 'Sales Records',
            'filename' => 'treklite-sales-records-' . now()->format('Ymd-His'),
            'subtitle' => $notes,
            'summary'  => [
                ['Completed sales', $active->count(), 'int'],
                ['Total revenue', (float) $active->sum('total_amount'), 'money'],
                ['Voided (not counted)', $voidedCount, 'int'],
            ],
            'columns' => [
                ['key' => 'invoice',   'label' => 'Invoice #',       'type' => 'text',     'align' => 'left',   'width' => 15],
                ['key' => 'date',      'label' => 'Date',            'type' => 'datetime', 'align' => 'left',   'width' => 19],
                ['key' => 'customer',  'label' => 'Customer',        'type' => 'text',     'align' => 'left',   'width' => 22],
                ['key' => 'contact',   'label' => 'Contact',         'type' => 'text',     'align' => 'left',   'width' => 15],
                ['key' => 'cashier',   'label' => 'Processed By',    'type' => 'text',     'align' => 'left',   'width' => 18],
                ['key' => 'payment',   'label' => 'Payment',         'type' => 'text',     'align' => 'left',   'width' => 13],
                ['key' => 'reference', 'label' => 'Reference no.',   'type' => 'text',     'align' => 'left',   'width' => 17],
                ['key' => 'total',     'label' => 'Total',           'type' => 'money',    'align' => 'right',  'width' => 14],
                ['key' => 'tendered',  'label' => 'Amount received', 'type' => 'money',    'align' => 'right',  'width' => 16, 'pdf' => false],
                ['key' => 'change',    'label' => 'Change',          'type' => 'money',    'align' => 'right',  'width' => 12, 'pdf' => false],
                ['key' => 'status',    'label' => 'Status',          'type' => 'text',     'align' => 'center', 'width' => 12],
                ['key' => 'reason',    'label' => 'Void reason',     'type' => 'text',     'align' => 'left',   'width' => 24, 'pdf' => false],
            ],
            'rows' => $sales->map(fn ($s) => [
                'invoice'   => $s->invoice_number,
                'date'      => $s->sold_at,
                'customer'  => $s->customer_name,
                'contact'   => $s->customer_contact,
                'cashier'   => $s->user?->name,
                'payment'   => $s->payment_label,
                'reference' => $s->payment_reference,
                'total'     => (float) $s->total_amount,
                'tendered'  => (float) $s->amount_tendered,
                'change'    => (float) $s->change_amount,
                'status'    => $s->isVoided() ? 'VOIDED' : 'Completed',
                'reason'    => $s->void_reason,
            ])->all(),
            'muted'  => $sales->keys()->filter(fn ($i) => $sales[$i]->isVoided())->values()->all(),
            'totals' => ['label' => 'Total (completed sales only)', 'values' => ['total' => (float) $active->sum('total_amount')]],
            'footnote'    => $voidedCount > 0 ? "Struck-through rows are voided sales. They are listed for reference and are not counted in the total." : null,
            'orientation' => 'landscape',
        ];

        return TableExport::download($data, $request->query('format') === 'pdf' ? 'pdf' : 'xlsx');
    }

    /** Filters used by Sales Records and its PDF / Excel export. Staff only ever see the sales they processed. */
    private function filteredSales(Request $request, bool $isOwner): array
    {
        $q       = trim((string) $request->query('q'));
        $from    = $this->parseDate($request->query('from'));
        $to      = $this->parseDate($request->query('to'));
        $cashier = $isOwner && $request->filled('cashier') ? (int) $request->query('cashier') : null;
        $payment = is_string($request->query('payment')) && array_key_exists($request->query('payment'), Sale::PAYMENT_LABELS) ? $request->query('payment') : '';

        $query = Sale::query()
            ->when(! $isOwner, fn ($w) => $w->where('user_id', auth()->id()))
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s
                ->where('invoice_number', 'like', "%{$q}%")
                ->orWhere('customer_name', 'like', "%{$q}%")
                ->orWhere('customer_contact', 'like', "%{$q}%")))
            ->when($from, fn ($w) => $w->whereDate('sold_at', '>=', $from->toDateString()))
            ->when($to, fn ($w) => $w->whereDate('sold_at', '<=', $to->toDateString()))
            ->when($cashier, fn ($w) => $w->where('user_id', $cashier))
            ->when($payment !== '', fn ($w) => $w->where('payment_method', $payment));

        return [$query, [
            'q' => $q, 'from' => $from?->toDateString(), 'to' => $to?->toDateString(),
            'cashier' => $cashier, 'payment' => $payment,
        ]];
    }

    /** Returns a date, or null if the text is empty or not a real date (so a bad URL never crashes the page). */
    private function parseDate($value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            return Carbon::createFromFormat('Y-m-d', trim($value))->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function create()
    {
        $products = Product::with('variants', 'brand', 'category')
            ->where('is_active', true)
            ->whereHas('variants')
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'id'       => $p->id,
                'name'     => $p->name,
                'category' => $p->category?->name,
                'brand'    => $p->brand?->name,
                'image'    => $p->image_url,
                'ph_tip'   => $p->placeholder['tip'],   // tooltip of the default placeholder picture
                'variants' => $p->variants->map(fn ($v) => [
                    'id'    => $v->id,
                    'size'  => $v->size?->name,
                    'color' => $v->color?->name,
                    'label' => $v->label,
                    'price' => (float) ($v->price ?? $p->price),
                    'stock' => (int) $v->quantity,
                ])->values(),
            ])->values();

        $categories = Category::whereIn('id', Product::where('is_active', true)->whereNotNull('category_id')->select('category_id'))
            ->orderBy('name')->pluck('name');

        // Optional: put your shop's static QR Ph / GCash QR image here to show it at checkout.
        $paymentQr = file_exists(public_path('images/payment-qr.png')) ? asset('images/payment-qr.png') : null;

        return view('sales.create', compact('products', 'categories', 'paymentQr'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_name'      => ['nullable', 'string', 'max:255'],
            'customer_address'   => ['nullable', 'string', 'max:255'],
            'customer_contact'   => ['nullable', 'string', 'max:50'],
            'payment_method'     => ['required', 'in:cash,gcash,bank_transfer'],
            'amount_tendered'    => ['required', 'numeric', 'min:0'],
            'payment_reference'  => ['required_unless:payment_method,cash', 'nullable', 'string', 'max:100'],
            'payment_proof'      => ['required_unless:payment_method,cash', 'nullable', 'image', 'max:4096'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity'   => ['required', 'integer', 'min:1'],
        ], [
            'amount_tendered.required'        => 'Enter the amount received from the customer.',
            'payment_reference.required_unless' => 'Enter the reference number from the payment confirmation for GCash / Bank Transfer.',
            'payment_proof.required_unless'   => 'Take or upload a photo of the payment confirmation for GCash / Bank Transfer.',
        ]);

        // Reference number is only for GCash / Bank Transfer. Cash sales store nothing (NULL), never an empty text.
        $reference = $data['payment_method'] === 'cash' ? null : trim((string) ($data['payment_reference'] ?? ''));
        $reference = $reference === '' ? null : $reference;

        // The same reference number must not be used on two different sales.
        if ($reference !== null) {
            $usedRef = Sale::where('payment_reference', $reference)->first();
            if ($usedRef) {
                throw ValidationException::withMessages(['payment_reference' => ['This reference number was already used on invoice ' . $usedRef->invoice_number . '. Check the number on the payment confirmation.']]);
            }
        }

        // The same payment photo must not be used on two different sales.
        $proofHash = null;
        if ($request->hasFile('payment_proof')) {
            $proofHash = sha1_file($request->file('payment_proof')->getRealPath());
            $used = Sale::where('payment_proof_hash', $proofHash)->first();
            if ($used) {
                throw ValidationException::withMessages(['payment_proof' => ['This payment photo was already used on invoice ' . $used->invoice_number . '. Take a new photo of this payment.']]);
            }
        }

        $sale = DB::transaction(function () use ($data, $request, $proofHash, $reference) {
            // Merge duplicate variants, then lock them so two sales cannot take the same stock.
            $lines = collect($data['items'])->groupBy('variant_id')->map(fn ($g) => (int) $g->sum('quantity'));

            $variants = ProductVariant::with('product')
                ->whereIn('id', $lines->keys())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // A cart left open on a screen must not sell an item that was archived in the meantime.
            $archived = $variants->filter(fn ($v) => ! $v->product->is_active)->map(fn ($v) => $v->product->name)->unique();
            if ($archived->isNotEmpty()) {
                throw ValidationException::withMessages(['items' => ['These items are no longer sold (archived): ' . $archived->implode(', ') . '. Remove them from the cart.']]);
            }

            $short = [];
            $total = 0.0;
            foreach ($lines as $id => $qty) {
                $v = $variants[$id];
                if ($qty > $v->quantity) {
                    $short[] = $v->product->name . ' (' . $v->label . '): only ' . $v->quantity . ' left';
                }
                $total += $v->selling_price * $qty;
            }
            if ($short) {
                throw ValidationException::withMessages(['items' => ['Not enough stock for: ' . implode('; ', $short)]]);
            }

            // The amount received is required and must cover the total. More than the total is fine (change is given).
            $tendered = (float) $data['amount_tendered'];
            if ($tendered + 0.001 < $total) {
                throw ValidationException::withMessages(['amount_tendered' => ['The amount received (₱' . number_format($tendered, 2) . ') is less than the total (₱' . number_format($total, 2) . ').']]);
            }

            // Invoice number = today's last number + 1. If two sales are saved at the same moment and pick the
            // same number, the database refuses the second one and we simply try the next number.
            $prefix = 'INV-' . now()->format('Ymd');
            $last   = Sale::where('invoice_number', 'like', $prefix . '-%')->lockForUpdate()->max('invoice_number');
            $seq    = $last ? ((int) substr($last, -4)) + 1 : 1;
            // The payment photo is saved inside the database (not as a file on the server), so it is not lost on restart.
            $proofId = $request->hasFile('payment_proof') ? ImageStore::fromUpload($request->file('payment_proof'), 1600) : null;

            $sale = null;
            for ($try = 0; $try < 5; $try++) {
                try {
                    $sale = Sale::create([
                        'invoice_number'     => sprintf('%s-%04d', $prefix, $seq + $try),
                        'user_id'            => auth()->id(),
                        'customer_name'      => $data['customer_name'] ?: 'Walk-in Customer',
                        'customer_address'   => $data['customer_address'] ?? null,
                        'customer_contact'   => $data['customer_contact'] ?? null,
                        'payment_method'     => $data['payment_method'],
                        'payment_reference'  => $reference,
                        'payment_proof_id'   => $proofId,
                        'payment_proof_hash' => $proofHash,
                        'amount_tendered'    => $tendered,
                        'change_amount'      => max(0, $tendered - $total),
                        'total_amount'       => $total,
                        'sold_at'            => now(),
                    ]);
                    break;
                } catch (UniqueConstraintViolationException $e) {
                    if ($try === 4) {
                        throw $e;
                    }
                }
            }

            foreach ($lines as $id => $qty) {
                $v = $variants[$id];
                $sale->items()->create([
                    'variant_id' => $v->id,
                    'quantity'   => $qty,
                    'unit_price' => $v->selling_price,
                    'subtotal'   => $v->selling_price * $qty,
                ]);

                // Selling is a stock change too, so it shows up in Stock History.
                $before = (int) $v->quantity;
                $v->update(['quantity' => $before - $qty]);
                StockMovement::create([
                    'variant_id'      => $v->id,
                    'user_id'         => auth()->id(),
                    'type'            => 'sale',
                    'quantity_before' => $before,
                    'quantity_change' => -$qty,
                    'quantity_after'  => $before - $qty,
                    'note'            => 'Sale ' . $sale->invoice_number,
                ]);
            }

            return $sale;
        });

        return redirect()->route('sales.show', $sale)->with('status', 'Sale recorded. Invoice ' . $sale->invoice_number . ' is ready to print.');
    }

    public function show(Sale $sale)
    {
        abort_unless(
            auth()->user()->hasFullAccess() || $sale->user_id === auth()->id(),
            403
        );

        $sale->load('items.variant.product', 'user', 'voidedBy');

        return view('sales.show', compact('sale'));
    }

    /**
     * Owner only: reverse a recorded sale (wrong item, customer returned everything, mistaken entry).
     * The sale stays on record as VOIDED (who, when, why), the items go back to stock and each
     * return appears in Stock History. A voided sale is no longer counted in totals and reports.
     */
    public function void(Request $request, Sale $sale)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'reason.required' => 'Please write why this sale is being voided.',
            'reason.min'      => 'The reason is too short. Please explain what happened.',
        ]);

        DB::transaction(function () use ($sale, $data) {
            $sale = Sale::lockForUpdate()->findOrFail($sale->id);

            if ($sale->isVoided()) {
                throw ValidationException::withMessages(['reason' => ['This sale was already voided.']]);
            }

            $sale->load('items');
            $variants = ProductVariant::whereIn('id', $sale->items->pluck('variant_id'))->lockForUpdate()->get()->keyBy('id');

            foreach ($sale->items as $item) {
                $v      = $variants[$item->variant_id];
                $before = (int) $v->quantity;
                $after  = $before + (int) $item->quantity;

                $v->update(['quantity' => $after]);
                StockMovement::create([
                    'variant_id'      => $v->id,
                    'user_id'         => auth()->id(),
                    'type'            => 'void',
                    'quantity_before' => $before,
                    'quantity_change' => (int) $item->quantity,
                    'quantity_after'  => $after,
                    'note'            => 'Void of ' . $sale->invoice_number . ': ' . $data['reason'],
                ]);
            }

            // The void is kept in its own table: which sale, who voided it, when and why.
            SaleVoid::create([
                'sale_id'     => $sale->id,
                'voided_by'   => auth()->id(),
                'void_reason' => $data['reason'],
                'voided_at'   => now(),
            ]);

            ActivityLog::record('sale_void', 'Voided ' . $sale->invoice_number . ' (₱' . number_format((float) $sale->total_amount, 2) . '). Reason: ' . $data['reason']);
        });

        return redirect()->route('sales.show', $sale)->with('status', 'Invoice ' . $sale->invoice_number . ' was voided and its items were returned to stock.');
    }
}
