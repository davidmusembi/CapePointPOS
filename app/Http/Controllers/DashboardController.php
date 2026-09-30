<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Services\ProfitService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        if (! $request->user()->can('dashboard.view')) {
            return view('dashboard.welcome');
        }

        $today = now()->toDateString();

        // Point-in-time balances (not affected by the date filter)
        $snapshot = [
            'receivables' => (float) Sale::sum('due_amount'),
            'overdue_receivables' => (float) Sale::where('due_amount', '>', 0)->whereDate('due_date', '<', $today)->sum('due_amount'),
            'payables' => (float) Purchase::sum('due_amount'),
            'overdue_payables' => (float) Purchase::where('due_amount', '>', 0)->whereDate('due_date', '<', $today)->sum('due_amount'),
            'low_stock' => Product::active()->lowStock()->count(),
            'out_of_stock' => Product::active()->where('track_stock', true)->where('stock_quantity', '<=', 0)->count(),
        ];

        return view('dashboard.index', [
            'snapshot' => $snapshot,
            'recentSales' => Sale::with('customer')->latest('date')->latest('id')->take(8)->get(),
            'lowStock' => Product::active()->lowStock()->with('unit')->orderBy('stock_quantity')->take(8)->get(),
            'topDebtors' => Customer::query()->withBalance()->having('balance', '>', 0.004)->orderByDesc('balance')->take(6)->get(),
            'overdueInvoices' => Sale::with('customer')->where('due_amount', '>', 0)->whereDate('due_date', '<', $today)->orderBy('due_date')->take(6)->get(),
            'topProducts' => app(ProfitService::class)->saleLines(now()->subDays(29)->toDateString(), $today)
                ->join('products', 'products.id', '=', 'si.product_id')
                ->groupBy('products.id', 'products.name')
                ->selectRaw('products.name, SUM(si.quantity - si.returned_quantity) as qty, SUM(si.net_amount * ns.scale) as revenue')
                ->orderByDesc('revenue')->take(6)->get(),
        ]);
    }

    /** Date-filtered dashboard figures (JSON) for the "Filter by date" control. */
    public function summary(Request $request, ProfitService $profit)
    {
        abort_unless($request->user()->can('dashboard.view'), 403);
        [$start, $end] = date_range_from_request($request, 'today');

        $p = $profit->summary($start, $end);
        $sales = Sale::whereBetween('date', [$start, $end]);
        $purchases = Purchase::whereBetween('date', [$start, $end]);

        return response()->json([
            'start' => $start,
            'end' => $end,
            'metrics' => [
                'total_sales' => $p['sales_total'],
                'invoice_count' => $p['invoice_count'],
                'net_sales' => $p['net_sales'],
                'invoice_due' => round((float) (clone $sales)->sum('due_amount'), 2),
                'sales_returns' => $p['returns_total'],
                'returns_count' => $p['returns_count'],
                'total_purchases' => round((float) (clone $purchases)->sum('total'), 2),
                'purchase_count' => (clone $purchases)->count(),
                'purchase_due' => round((float) (clone $purchases)->sum('due_amount'), 2),
                'purchase_returns' => round((float) PurchaseReturn::whereBetween('date', [$start, $end])->sum('total'), 2),
                'expenses' => $p['expenses'],
                'gross_profit' => $p['gross_profit'],
                'net_profit' => $p['net_profit'],
                'received' => round((float) Payment::where('party_type', 'customer')->whereBetween('date', [$start, $end])->sum('amount'), 2),
                'paid_out' => round((float) Payment::where('party_type', 'supplier')->whereBetween('date', [$start, $end])->sum('amount'), 2),
            ],
        ]);
    }

    /** Daily sales vs purchases for the chart (JSON). */
    public function chart(Request $request)
    {
        abort_unless($request->user()->can('dashboard.view'), 403);
        $days = in_array((int) $request->days, [7, 30, 90], true) ? (int) $request->days : 30;
        $from = now()->subDays($days - 1)->startOfDay();

        $sales = Sale::where('date', '>=', $from->toDateString())->toBase()->groupBy('date')
            ->selectRaw('DATE(date) d, SUM(total) t')->pluck('t', 'd');
        $purchases = Purchase::where('date', '>=', $from->toDateString())->toBase()->groupBy('date')
            ->selectRaw('DATE(date) d, SUM(total) t')->pluck('t', 'd');

        $labels = $salesSeries = $purchaseSeries = [];
        for ($d = $from->copy(); $d->lte(now()); $d->addDay()) {
            $key = $d->toDateString();
            $labels[] = $d->format('d M');
            $salesSeries[] = round((float) ($sales[$key] ?? 0), 2);
            $purchaseSeries[] = round((float) ($purchases[$key] ?? 0), 2);
        }

        return response()->json([
            'labels' => $labels,
            'sales' => $salesSeries,
            'purchases' => $purchaseSeries,
            'totals' => ['sales' => round(array_sum($salesSeries), 2), 'purchases' => round(array_sum($purchaseSeries), 2)],
        ]);
    }
}
