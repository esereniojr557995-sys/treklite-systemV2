<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // Staff only handle sales; the dashboard is for the Owner.
        if (! auth()->user()->hasFullAccess()) {
            return redirect()->route('sales.create');
        }

        // 1. Sales summary
        $totals = fn ($from) => Sale::active()->where('sold_at', '>=', $from)
            ->selectRaw('COALESCE(SUM(total_amount), 0) as total, COUNT(*) as cnt')
            ->first();

        $today = $totals(today());
        $week  = $totals(now()->startOfWeek());
        $month = $totals(now()->startOfMonth());

        $byPayment = Sale::active()->where('sold_at', '>=', now()->startOfMonth())
            ->selectRaw('payment_method, SUM(total_amount) as total, COUNT(*) as cnt')
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        // 2. Inventory summary
        $productCount = Product::active()->count();
        $unitsOnHand  = (int) ProductVariant::whereHas('product', fn ($p) => $p->where('is_active', true))->sum('quantity');
        $stockValue   = (float) DB::table('product_variants as v')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('p.is_active', true)
            ->selectRaw('COALESCE(SUM(v.quantity * COALESCE(v.price, p.price)), 0) as value')
            ->value('value');

        // 3. Needs attention
        $outOfStock = ProductVariant::with('product')
            ->whereHas('product', fn ($p) => $p->where('is_active', true))
            ->where('quantity', '<=', 0)->get();
        $lowStock   = ProductVariant::with('product')
            ->whereHas('product', fn ($p) => $p->where('is_active', true))
            ->where('quantity', '>', 0)
            ->whereColumn('quantity', '<=', 'low_stock_threshold')
            ->orderBy('quantity')
            ->get();

        // 4. Recent transactions
        // 5 per page, each panel has its own page number so they do not affect each other.
        $recentSales = Sale::with('user')->latest('sold_at')
            ->simplePaginate(5, ['*'], 'sales_page')->fragment('recent')->withQueryString();
        $recentMovements = StockMovement::with('variant.product', 'user')->latest('id')
            ->simplePaginate(5, ['*'], 'moves_page')->fragment('recent')->withQueryString();

        return view('dashboard', compact(
            'today', 'week', 'month', 'byPayment',
            'productCount', 'unitsOnHand', 'stockValue',
            'outOfStock', 'lowStock',
            'recentSales', 'recentMovements'
        ));
    }
}
