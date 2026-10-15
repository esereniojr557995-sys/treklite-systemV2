<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Support\TableExport;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function sales(Request $request)
    {
        return view('reports.sales', $this->salesData($request));
    }

    /** The Sales Report as a PDF (?format=pdf) or an Excel file (?format=xlsx, the default): same period and numbers as the page. */
    public function export(Request $request)
    {
        extract($this->salesData($request));   // sales, from, to, period, totalRevenue, byPayment, voidedCount, voidedTotal ...

        $periodText = $from->isSameDay($to)
            ? $from->format('M d, Y')
            : $from->format('M d, Y') . ' – ' . $to->format('M d, Y');

        $summary = [
            ['Total revenue', (float) $totalRevenue, 'money'],
            ['Transactions', $sales->count(), 'int'],
        ];
        foreach (Sale::PAYMENT_LABELS as $key => $label) {
            // Old methods (PayMaya / Card) only show when they have sales in this period.
            if (! isset(Sale::PAYMENT_METHODS[$key]) && ($byPayment[$key]['total'] ?? 0) <= 0) {
                continue;
            }
            $summary[] = [$label . ' (' . ($byPayment[$key]['count'] ?? 0) . ')', (float) ($byPayment[$key]['total'] ?? 0), 'money'];
        }

        $data = [
            'title'    => 'Sales Report',
            'filename' => 'treklite-sales-report-' . $from->format('Ymd') . '-' . $to->format('Ymd'),
            'subtitle' => ['Period: ' . $periodText],
            'summary'  => $summary,
            'columns'  => [
                ['key' => 'invoice',  'label' => 'Invoice #',    'type' => 'text',     'align' => 'left',  'width' => 16],
                ['key' => 'date',     'label' => 'Date',         'type' => 'datetime', 'align' => 'left',  'width' => 20],
                ['key' => 'customer', 'label' => 'Customer',     'type' => 'text',     'align' => 'left',  'width' => 24],
                ['key' => 'cashier',  'label' => 'Processed By', 'type' => 'text',     'align' => 'left',  'width' => 20],
                ['key' => 'payment',  'label' => 'Payment',      'type' => 'text',     'align' => 'left',  'width' => 14],
                ['key' => 'total',    'label' => 'Total',        'type' => 'money',    'align' => 'right', 'width' => 16],
            ],
            'rows' => $sales->values()->map(fn ($s) => [
                'invoice'  => $s->invoice_number,
                'date'     => $s->sold_at,
                'customer' => $s->customer_name,
                'cashier'  => $s->user?->name,
                'payment'  => $s->payment_label,
                'total'    => (float) $s->total_amount,
            ])->all(),
            'totals'      => ['label' => 'Total', 'values' => ['total' => (float) $totalRevenue]],
            'footnote'    => $voidedCount > 0
                ? $voidedCount . ' voided sale(s) (₱' . number_format($voidedTotal, 2) . ') in this period are not included.'
                : null,
            'orientation' => 'portrait',
        ];

        return TableExport::download($data, $request->query('format') === 'pdf' ? 'pdf' : 'xlsx');
    }

    /** Everything the Sales Report needs, worked out from the period in the link. */
    private function salesData(Request $request): array
    {
        $periods = ['day', 'week', 'month', 'year', 'custom'];
        $period  = in_array($request->query('period'), $periods, true) ? $request->query('period') : null;

        // Old links (?from=&to=) keep working and are treated as a custom range.
        if ($period === null) {
            $period = ($request->filled('from') || $request->filled('to')) ? 'custom' : 'month';
        }

        $date = $this->safeDate($request->query('date'), now());
        if ($date->gt(now())) { $date = now(); }   // no future dates

        if ($period === 'custom') {
            $from = $this->safeDate($request->query('from'), now()->startOfMonth())->startOfDay();
            $to   = $this->safeDate($request->query('to'), now())->endOfDay();
            if ($from->gt(now())) { $from = now()->startOfDay(); }   // no future dates
            if ($to->gt(now()))   { $to   = now()->endOfDay(); }
            if ($from->gt($to)) {
                [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
            }
            $prevDate = $nextDate = $date;
        } else {
            [$from, $to] = match ($period) {
                'day'   => [$date->copy()->startOfDay(),   $date->copy()->endOfDay()],
                'week'  => [$date->copy()->startOfWeek(),  $date->copy()->endOfWeek()],
                'month' => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()],
                'year'  => [$date->copy()->startOfYear(),  $date->copy()->endOfYear()],
            };
            $prevDate = match ($period) {
                'day' => $date->copy()->subDay(), 'week' => $date->copy()->subWeek(),
                'month' => $date->copy()->subMonthNoOverflow(), 'year' => $date->copy()->subYearNoOverflow(),
            };
            $nextDate = match ($period) {
                'day' => $date->copy()->addDay(), 'week' => $date->copy()->addWeek(),
                'month' => $date->copy()->addMonthNoOverflow(), 'year' => $date->copy()->addYearNoOverflow(),
            };
        }

        // The range never reaches past today.
        if ($to->gt(now())) { $to = now()->endOfDay(); }

        // Never step into the future.
        if ($nextDate->gt(now())) { $nextDate = now(); }

        $sales = Sale::with('user')->active()
            ->whereBetween('sold_at', [$from, $to])
            ->orderBy('sold_at')
            ->get();

        // Voided sales are not counted in the report, but the Owner is told how many were left out.
        $voided      = Sale::whereHas('voidRecord')->whereBetween('sold_at', [$from, $to]);
        $voidedCount = (clone $voided)->count();
        $voidedTotal = (float) (clone $voided)->sum('total_amount');

        $totalRevenue = $sales->sum('total_amount');
        $byPayment    = $sales->groupBy('payment_method')->map(fn ($g) => [
            'count' => $g->count(),
            'total' => $g->sum('total_amount'),
        ]);

        return compact('sales', 'from', 'to', 'period', 'date', 'prevDate', 'nextDate', 'totalRevenue', 'byPayment', 'voidedCount', 'voidedTotal');
    }

    /** A date from the link, or the default when it is empty or not a real date (so a bad link never crashes the page). */
    private function safeDate($value, Carbon $default): Carbon
    {
        try {
            return is_string($value) && trim($value) !== '' ? Carbon::parse($value) : $default->copy();
        } catch (\Throwable $e) {
            return $default->copy();
        }
    }
}
