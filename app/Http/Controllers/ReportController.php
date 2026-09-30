<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

/**
 * All back-office reports. Each action renders the page on a normal request and serves its data
 * on AJAX requests: server-side DataTables (Yajra) for document lists, and {data, totals} JSON for
 * aggregated tables (rendered by client-side DataTables). The "section" parameter selects the dataset.
 */
class ReportController extends Controller
{
    /* =====================================================================
     | 1. Purchase & Sale
     * =================================================================== */
    public function purchaseSale(Request $request)
    {
        [$start, $end] = date_range_from_request($request);

        if ($request->ajax()) {
            $p = Purchase::whereBetween('date', [$start, $end])->toBase()
                ->selectRaw('COUNT(*) cnt, COALESCE(SUM(subtotal - discount_amount),0) excl, COALESCE(SUM(total),0) incl, COALESCE(SUM(tax_amount),0) tax, COALESCE(SUM(shipping_charges + additional_charges_total),0) charges, COALESCE(SUM(due_amount),0) due')->first();
            $s = Sale::whereBetween('date', [$start, $end])->toBase()
                ->selectRaw('COUNT(*) cnt, COALESCE(SUM(subtotal - discount_amount),0) excl, COALESCE(SUM(total),0) incl, COALESCE(SUM(tax_amount),0) tax, COALESCE(SUM(shipping_charges + additional_charges_total),0) charges, COALESCE(SUM(due_amount),0) due')->first();
            $pr = (float) PurchaseReturn::whereBetween('date', [$start, $end])->sum('total');
            $sr = (float) SaleReturn::whereBetween('date', [$start, $end])->sum('total');

            return response()->json(['totals' => $this->num([
                'purchase_count' => $p->cnt, 'purchase_excl' => $p->excl, 'purchase_incl' => $p->incl,
                'purchase_tax' => $p->tax, 'purchase_charges' => $p->charges, 'purchase_return' => $pr, 'purchase_due' => $p->due,
                'sale_count' => $s->cnt, 'sale_excl' => $s->excl, 'sale_incl' => $s->incl,
                'sale_tax' => $s->tax, 'sale_charges' => $s->charges, 'sale_return' => $sr, 'sale_due' => $s->due,
                'overall' => ($s->incl - $sr) - ($p->incl - $pr),
                'overall_due' => $s->due - $p->due,
            ])]);
        }

        return view('reports.purchase-sale', compact('start', 'end'));
    }

    /* =====================================================================
     | 2. Sales summary
     * =================================================================== */
    public function sales(Request $request)
    {
        [$start, $end] = date_range_from_request($request);

        if ($request->ajax()) {
            $filter = function ($q, string $t = 'sales') use ($request, $start, $end) {
                return $q->whereBetween("$t.date", [$start, $end])
                    ->when($request->customer_id, fn ($q, $v) => $q->where("$t.customer_id", $v))
                    ->when($request->payment_status, fn ($q, $v) => $q->where("$t.payment_status", $v));
            };

            if ($request->section === 'products') {
                $netSums = DB::table('sale_items')->groupBy('sale_id')->selectRaw('sale_id, SUM(net_amount) n');
                $rows = $filter(DB::table('sale_items as si')->join('sales', 'sales.id', '=', 'si.sale_id'))
                    ->joinSub($netSums, 'ns', 'ns.sale_id', '=', 'sales.id')
                    ->join('products as p', 'p.id', '=', 'si.product_id')
                    ->whereNull('sales.deleted_at')
                    ->groupBy('p.id', 'p.name', 'p.sku')
                    ->selectRaw('p.id, p.name, p.sku, SUM(si.quantity) qty, SUM(si.returned_quantity) returned, SUM(CASE WHEN ns.n > 0 THEN si.net_amount * (sales.subtotal - sales.discount_amount) / ns.n ELSE 0 END) revenue, SUM(si.tax_amount) tax')
                    ->orderByDesc('revenue')->get()
                    ->map(fn ($r) => [
                        'product' => $this->productLink($r->id, $r->name), 'sku' => e($r->sku),
                        'qty' => (float) $r->qty, 'returned' => (float) $r->returned, 'net_qty' => (float) $r->qty - (float) $r->returned,
                        'revenue' => round((float) $r->revenue, 2), 'tax' => round((float) $r->tax, 2),
                        'avg_price' => $r->qty > 0 ? round($r->revenue / $r->qty, 2) : 0,
                    ]);

                return $this->json($rows, [
                    'p_qty' => $rows->sum('qty'), 'p_returned' => $rows->sum('returned'), 'p_net_qty' => $rows->sum('net_qty'),
                    'p_revenue' => $rows->sum('revenue'), 'p_tax' => $rows->sum('tax'),
                ]);
            }

            if ($request->section === 'daily') {
                $rows = $filter(Sale::query())->toBase()->groupBy('date')
                    ->selectRaw('date, COUNT(*) cnt, SUM(subtotal - discount_amount) net, SUM(tax_amount) tax, SUM(shipping_charges + additional_charges_total) charges, SUM(total) total, SUM(paid_amount) paid, SUM(due_amount) due')
                    ->orderBy('date')->get()
                    ->map(fn ($r) => [
                        'date' => ['display' => format_date($r->date), 'sort' => $r->date],
                        'count' => (int) $r->cnt, 'net' => (float) $r->net, 'tax' => (float) $r->tax, 'charges' => (float) $r->charges,
                        'total' => (float) $r->total, 'paid' => (float) $r->paid, 'due' => (float) $r->due,
                    ]);

                return $this->json($rows, [
                    'd_count' => $rows->sum('count'), 'd_net' => $rows->sum('net'), 'd_tax' => $rows->sum('tax'), 'd_charges' => $rows->sum('charges'),
                    'd_total' => $rows->sum('total'), 'd_paid' => $rows->sum('paid'), 'd_due' => $rows->sum('due'),
                ]);
            }

            $base = $filter(Sale::query());
            $totals = (clone $base)->toBase()->selectRaw('COUNT(*) count, COALESCE(SUM(subtotal),0) subtotal, COALESCE(SUM(discount_amount),0) discount,
                COALESCE(SUM(tax_amount),0) tax, COALESCE(SUM(shipping_charges + additional_charges_total),0) charges, COALESCE(SUM(total),0) total, COALESCE(SUM(paid_amount),0) paid,
                COALESCE(SUM(returned_amount),0) returned, COALESCE(SUM(due_amount),0) due')->first();

            return DataTables::eloquent($base->with('customer'))
                ->editColumn('date', fn ($s) => format_date($s->date))
                ->editColumn('invoice_no', fn ($s) => '<a href="'.route('sales.show', $s->id).'">'.e($s->invoice_no).'</a>')
                ->addColumn('customer_name', fn ($s) => e($s->customer->display_name ?? '-'))
                ->addColumn('charges', fn ($s) => $s->charges_total)
                ->filterColumn('customer_name', fn ($q, $k) => $this->whereContact($q, 'customer', $k))
                ->editColumn('payment_status', fn ($s) => payment_status_badge($s->payment_status))
                ->with('totals', $this->num((array) $totals))
                ->rawColumns(['invoice_no', 'payment_status', 'customer_name'])
                ->make(true);
        }

        return view('reports.sales', ['start' => $start, 'end' => $end, 'customers' => $this->customers()]);
    }

    /* =====================================================================
     | 3. Sales due / receivables
     * =================================================================== */
    public function salesDue(Request $request)
    {
        [$start, $end] = date_range_from_request($request, 'all');
        $today = now()->toDateString();

        if ($request->ajax()) {
            $base = Sale::query()->where('due_amount', '>', 0)->whereBetween('date', [$start, $end])
                ->when($request->customer_id, fn ($q, $v) => $q->where('customer_id', $v));

            if ($request->section === 'customers') {
                $groups = (clone $base)->toBase()->groupBy('customer_id')
                    ->selectRaw('customer_id, COUNT(*) cnt, SUM(due_amount) due, MIN(due_date) oldest_due,
                        SUM(CASE WHEN due_date < ? THEN due_amount ELSE 0 END) overdue', [$today])->get();
                $contacts = Customer::withTrashed()->withBalance()->whereIn('customers.id', $groups->pluck('customer_id'))->get()->keyBy('id');

                $rows = $groups->map(function ($g) use ($contacts) {
                    $c = $contacts[$g->customer_id] ?? null;

                    return [
                        'customer' => $c ? '<a href="'.route('customers.show', $c->id).'">'.e($c->display_name).'</a>' : '-',
                        'code' => e($c->code ?? ''), 'phone' => e($c->phone ?? ''),
                        'count' => (int) $g->cnt, 'due' => round((float) $g->due, 2),
                        'oldest_due' => ['display' => format_date($g->oldest_due), 'sort' => (string) $g->oldest_due],
                        'overdue' => round((float) $g->overdue, 2),
                        'balance' => round((float) ($c->balance ?? 0), 2),
                        'action' => $c ? '<a href="'.route('customers.statement', $c->id).'" class="btn btn-xs btn-light-primary"><i class="fas fa-file-alt"></i> Statement</a>' : '',
                    ];
                })->sortByDesc('due')->values();

                return $this->json($rows, [
                    'c_count' => $rows->sum('count'), 'c_due' => $rows->sum('due'),
                    'c_overdue' => $rows->sum('overdue'), 'c_balance' => $rows->sum('balance'), 'c_customers' => $rows->count(),
                ]);
            }

            $totals = (clone $base)->toBase()->selectRaw('COUNT(*) count, COALESCE(SUM(total),0) total, COALESCE(SUM(paid_amount),0) paid,
                COALESCE(SUM(returned_amount),0) returned, COALESCE(SUM(due_amount),0) due,
                COALESCE(SUM(CASE WHEN due_date < ? THEN due_amount ELSE 0 END),0) overdue', [$today])->first();

            return DataTables::eloquent($base->with('customer'))
                ->editColumn('date', fn ($s) => format_date($s->date))
                ->editColumn('due_date', fn ($s) => format_date($s->due_date))
                ->editColumn('invoice_no', fn ($s) => '<a href="'.route('sales.show', $s->id).'">'.e($s->invoice_no).'</a>')
                ->addColumn('customer_name', fn ($s) => e($s->customer->display_name ?? '-'))
                ->addColumn('charges', fn ($s) => $s->charges_total)
                ->filterColumn('customer_name', fn ($q, $k) => $this->whereContact($q, 'customer', $k))
                ->addColumn('days_overdue', fn ($s) => $this->overdueBadge($s->due_date))
                ->editColumn('payment_status', fn ($s) => payment_status_badge($s->payment_status))
                ->with('totals', $this->num((array) $totals))
                ->rawColumns(['invoice_no', 'days_overdue', 'payment_status', 'customer_name'])
                ->make(true);
        }

        return view('reports.sales-due', ['start' => $start, 'end' => $end, 'customers' => $this->customers()]);
    }

    /* =====================================================================
     | 4. Aging (sales due)
     * =================================================================== */
    public function aging(Request $request)
    {
        $asOf = Carbon::parse($request->input('as_of') ?: now())->startOfDay();
        $ageBy = $request->input('age_by') === 'due' ? 'due' : 'invoice';

        if ($request->ajax()) {
            $sales = Sale::with('customer')->where('due_amount', '>', 0)->where('date', '<=', $asOf->toDateString())
                ->when($request->customer_id, fn ($q, $v) => $q->where('customer_id', $v))
                ->orderBy('date')->get();

            $invoices = $sales->map(function (Sale $s) use ($asOf, $ageBy) {
                $ref = $ageBy === 'due' ? ($s->due_date ?? $s->date) : $s->date;
                $days = (int) floor($ref->copy()->startOfDay()->diffInDays($asOf, false));
                $bucket = $this->bucket($days, $ageBy === 'due');

                return ['sale' => $s, 'days' => $days, 'bucket' => $bucket];
            });

            if ($request->section === 'invoices') {
                $labels = $this->bucketLabels();
                $rows = $invoices->map(fn ($i) => [
                    'invoice' => '<a href="'.route('sales.show', $i['sale']->id).'">'.e($i['sale']->invoice_no).'</a>',
                    'customer' => e($i['sale']->customer->display_name ?? '-'),
                    'date' => ['display' => format_date($i['sale']->date), 'sort' => $i['sale']->date->toDateString()],
                    'due_date' => ['display' => format_date($i['sale']->due_date), 'sort' => optional($i['sale']->due_date)->toDateString()],
                    'days' => max(0, $i['days']),
                    'bucket' => $labels[$i['bucket']],
                    'total' => $i['sale']->total, 'paid' => $i['sale']->paid_amount, 'due' => $i['sale']->due_amount,
                ])->values();

                return $this->json($rows, ['i_total' => $rows->sum('total'), 'i_paid' => $rows->sum('paid'), 'i_due' => $rows->sum('due')]);
            }

            $empty = ['current' => 0, 'b30' => 0, 'b60' => 0, 'b90' => 0, 'b90p' => 0];
            $rows = $invoices->groupBy(fn ($i) => $i['sale']->customer_id)->map(function ($group) use ($empty) {
                $c = $group->first()['sale']->customer;
                $b = $empty;
                foreach ($group as $i) {
                    $b[$i['bucket']] += $i['sale']->due_amount;
                }
                $b = array_map(fn ($v) => round($v, 2), $b);

                return [
                    'customer' => $c ? '<a href="'.route('customers.statement', $c->id).'">'.e($c->display_name).'</a>' : '-',
                    'code' => e($c->code ?? ''),
                    'invoices' => $group->count(),
                ] + $b + ['total' => round(array_sum($b), 2)];
            })->sortByDesc('total')->values();

            $totals = ['invoices' => $rows->sum('invoices'), 'total' => $rows->sum('total')];
            foreach (array_keys($empty) as $k) {
                $totals[$k] = $rows->sum($k);
            }

            return $this->json($rows, $totals);
        }

        return view('reports.aging', [
            'asOf' => $asOf->toDateString(), 'ageBy' => $ageBy, 'customers' => $this->customers(), 'labels' => $this->bucketLabels(),
        ]);
    }

    /* =====================================================================
     | 5. Sales returns
     * =================================================================== */
    public function saleReturns(Request $request)
    {
        [$start, $end] = date_range_from_request($request);

        if ($request->ajax()) {
            if ($request->section === 'products') {
                $rows = DB::table('sale_return_items as ri')->join('sale_returns as r', 'r.id', '=', 'ri.sale_return_id')
                    ->join('products as p', 'p.id', '=', 'ri.product_id')
                    ->whereNull('r.deleted_at')->whereBetween('r.date', [$start, $end])
                    ->when($request->customer_id, fn ($q, $v) => $q->where('r.customer_id', $v))
                    ->groupBy('p.id', 'p.name', 'p.sku')
                    ->selectRaw('p.id, p.name, p.sku, COUNT(DISTINCT r.id) returns, SUM(ri.quantity) qty, SUM(ri.line_total) amount, SUM(ri.quantity * ri.unit_cost) cost')
                    ->orderByDesc('amount')->get()
                    ->map(fn ($r) => [
                        'product' => $this->productLink($r->id, $r->name), 'sku' => e($r->sku), 'returns' => (int) $r->returns,
                        'qty' => (float) $r->qty, 'amount' => round((float) $r->amount, 2), 'cost' => round((float) $r->cost, 2),
                    ]);

                return $this->json($rows, ['p_qty' => $rows->sum('qty'), 'p_amount' => $rows->sum('amount'), 'p_cost' => $rows->sum('cost')]);
            }

            $base = SaleReturn::query()->whereBetween('date', [$start, $end])
                ->when($request->customer_id, fn ($q, $v) => $q->where('customer_id', $v));
            $totals = (clone $base)->toBase()->selectRaw('COUNT(*) count, COALESCE(SUM(subtotal),0) subtotal, COALESCE(SUM(tax_amount),0) tax,
                COALESCE(SUM(discount_amount),0) discount, COALESCE(SUM(total),0) total, COALESCE(SUM(refund_amount),0) refund')->first();

            return DataTables::eloquent($base->with(['sale', 'customer']))
                ->editColumn('date', fn ($r) => format_date($r->date))
                ->editColumn('return_no', fn ($r) => '<a href="'.route('sale-returns.show', $r->id).'">'.e($r->return_no).'</a>')
                ->addColumn('invoice_no', fn ($r) => $r->sale ? '<a href="'.route('sales.show', $r->sale_id).'">'.e($r->sale->invoice_no).'</a>' : '-')
                ->addColumn('customer_name', fn ($r) => e($r->customer->display_name ?? '-'))
                ->filterColumn('customer_name', fn ($q, $k) => $this->whereContact($q, 'customer', $k))
                ->with('totals', $this->num((array) $totals))
                ->rawColumns(['return_no', 'invoice_no', 'customer_name'])
                ->make(true);
        }

        return view('reports.sale-returns', ['start' => $start, 'end' => $end, 'customers' => $this->customers()]);
    }

    /* =====================================================================
     | 6. Purchase summary
     * =================================================================== */
    public function purchases(Request $request)
    {
        [$start, $end] = date_range_from_request($request);

        if ($request->ajax()) {
            $filter = function ($q, string $t = 'purchases') use ($request, $start, $end) {
                return $q->whereBetween("$t.date", [$start, $end])
                    ->when($request->supplier_id, fn ($q, $v) => $q->where("$t.supplier_id", $v))
                    ->when($request->payment_status, fn ($q, $v) => $q->where("$t.payment_status", $v));
            };

            if ($request->section === 'products') {
                $rows = $filter(DB::table('purchase_items as pi')->join('purchases', 'purchases.id', '=', 'pi.purchase_id'))
                    ->join('products as p', 'p.id', '=', 'pi.product_id')
                    ->whereNull('purchases.deleted_at')
                    ->groupBy('p.id', 'p.name', 'p.sku')
                    ->selectRaw('p.id, p.name, p.sku, SUM(pi.quantity) qty, SUM(pi.returned_quantity) returned, SUM(pi.net_amount) amount, SUM(pi.tax_amount) tax')
                    ->orderByDesc('amount')->get()
                    ->map(fn ($r) => [
                        'product' => $this->productLink($r->id, $r->name), 'sku' => e($r->sku),
                        'qty' => (float) $r->qty, 'returned' => (float) $r->returned, 'net_qty' => (float) $r->qty - (float) $r->returned,
                        'amount' => round((float) $r->amount, 2), 'tax' => round((float) $r->tax, 2),
                        'avg_cost' => $r->qty > 0 ? round($r->amount / $r->qty, 2) : 0,
                    ]);

                return $this->json($rows, [
                    'p_qty' => $rows->sum('qty'), 'p_returned' => $rows->sum('returned'), 'p_net_qty' => $rows->sum('net_qty'),
                    'p_amount' => $rows->sum('amount'), 'p_tax' => $rows->sum('tax'),
                ]);
            }

            $base = $filter(Purchase::query());
            $totals = (clone $base)->toBase()->selectRaw('COUNT(*) count, COALESCE(SUM(subtotal),0) subtotal, COALESCE(SUM(discount_amount),0) discount,
                COALESCE(SUM(tax_amount),0) tax, COALESCE(SUM(shipping_charges + additional_charges_total),0) charges, COALESCE(SUM(total),0) total, COALESCE(SUM(paid_amount),0) paid,
                COALESCE(SUM(returned_amount),0) returned, COALESCE(SUM(due_amount),0) due')->first();

            return DataTables::eloquent($base->with('supplier'))
                ->editColumn('date', fn ($p) => format_date($p->date))
                ->editColumn('purchase_no', fn ($p) => '<a href="'.route('purchases.show', $p->id).'">'.e($p->purchase_no).'</a>')
                ->addColumn('supplier_name', fn ($p) => e($p->supplier->display_name ?? '-'))
                ->addColumn('charges', fn ($p) => $p->charges_total)
                ->filterColumn('supplier_name', fn ($q, $k) => $this->whereContact($q, 'supplier', $k))
                ->editColumn('payment_status', fn ($p) => payment_status_badge($p->payment_status))
                ->with('totals', $this->num((array) $totals))
                ->rawColumns(['purchase_no', 'payment_status', 'supplier_name'])
                ->make(true);
        }

        return view('reports.purchases', ['start' => $start, 'end' => $end, 'suppliers' => $this->suppliers()]);
    }

    /* =====================================================================
     | 7. Purchase returns
     * =================================================================== */
    public function purchaseReturns(Request $request)
    {
        [$start, $end] = date_range_from_request($request);

        if ($request->ajax()) {
            if ($request->section === 'products') {
                $rows = DB::table('purchase_return_items as ri')->join('purchase_returns as r', 'r.id', '=', 'ri.purchase_return_id')
                    ->join('products as p', 'p.id', '=', 'ri.product_id')
                    ->whereNull('r.deleted_at')->whereBetween('r.date', [$start, $end])
                    ->when($request->supplier_id, fn ($q, $v) => $q->where('r.supplier_id', $v))
                    ->groupBy('p.id', 'p.name', 'p.sku')
                    ->selectRaw('p.id, p.name, p.sku, COUNT(DISTINCT r.id) returns, SUM(ri.quantity) qty, SUM(ri.line_total) amount')
                    ->orderByDesc('amount')->get()
                    ->map(fn ($r) => [
                        'product' => $this->productLink($r->id, $r->name), 'sku' => e($r->sku), 'returns' => (int) $r->returns,
                        'qty' => (float) $r->qty, 'amount' => round((float) $r->amount, 2),
                    ]);

                return $this->json($rows, ['p_qty' => $rows->sum('qty'), 'p_amount' => $rows->sum('amount')]);
            }

            $base = PurchaseReturn::query()->whereBetween('date', [$start, $end])
                ->when($request->supplier_id, fn ($q, $v) => $q->where('supplier_id', $v));
            $totals = (clone $base)->toBase()->selectRaw('COUNT(*) count, COALESCE(SUM(subtotal),0) subtotal, COALESCE(SUM(tax_amount),0) tax,
                COALESCE(SUM(discount_amount),0) discount, COALESCE(SUM(total),0) total, COALESCE(SUM(refund_amount),0) refund')->first();

            return DataTables::eloquent($base->with(['purchase', 'supplier']))
                ->editColumn('date', fn ($r) => format_date($r->date))
                ->editColumn('return_no', fn ($r) => '<a href="'.route('purchase-returns.show', $r->id).'">'.e($r->return_no).'</a>')
                ->addColumn('purchase_no', fn ($r) => $r->purchase ? '<a href="'.route('purchases.show', $r->purchase_id).'">'.e($r->purchase->purchase_no).'</a>' : '-')
                ->addColumn('supplier_name', fn ($r) => e($r->supplier->display_name ?? '-'))
                ->filterColumn('supplier_name', fn ($q, $k) => $this->whereContact($q, 'supplier', $k))
                ->with('totals', $this->num((array) $totals))
                ->rawColumns(['return_no', 'purchase_no', 'supplier_name'])
                ->make(true);
        }

        return view('reports.purchase-returns', ['start' => $start, 'end' => $end, 'suppliers' => $this->suppliers()]);
    }

    /* =====================================================================
     | 8. Supplier payables
     * =================================================================== */
    public function payables(Request $request)
    {
        [$start, $end] = date_range_from_request($request, 'all');
        $today = now()->toDateString();

        if ($request->ajax()) {
            $base = Purchase::query()->where('due_amount', '>', 0)->whereBetween('date', [$start, $end])
                ->when($request->supplier_id, fn ($q, $v) => $q->where('supplier_id', $v));

            if ($request->section === 'suppliers') {
                $groups = (clone $base)->toBase()->groupBy('supplier_id')
                    ->selectRaw('supplier_id, COUNT(*) cnt, SUM(due_amount) due,
                        SUM(CASE WHEN due_date < ? THEN due_amount ELSE 0 END) overdue,
                        SUM(CASE WHEN DATEDIFF(?, date) <= 30 THEN due_amount ELSE 0 END) b30,
                        SUM(CASE WHEN DATEDIFF(?, date) BETWEEN 31 AND 60 THEN due_amount ELSE 0 END) b60,
                        SUM(CASE WHEN DATEDIFF(?, date) BETWEEN 61 AND 90 THEN due_amount ELSE 0 END) b90,
                        SUM(CASE WHEN DATEDIFF(?, date) > 90 THEN due_amount ELSE 0 END) b90p', [$today, $today, $today, $today, $today])->get();
                $contacts = Supplier::withTrashed()->withBalance()->whereIn('suppliers.id', $groups->pluck('supplier_id'))->get()->keyBy('id');

                $rows = $groups->map(function ($g) use ($contacts) {
                    $c = $contacts[$g->supplier_id] ?? null;

                    return [
                        'supplier' => $c ? '<a href="'.route('suppliers.show', $c->id).'">'.e($c->display_name).'</a>' : '-',
                        'code' => e($c->code ?? ''), 'phone' => e($c->phone ?? ''), 'count' => (int) $g->cnt,
                        'due' => round((float) $g->due, 2), 'overdue' => round((float) $g->overdue, 2),
                        'b30' => round((float) $g->b30, 2), 'b60' => round((float) $g->b60, 2),
                        'b90' => round((float) $g->b90, 2), 'b90p' => round((float) $g->b90p, 2),
                        'balance' => round((float) ($c->balance ?? 0), 2),
                        'action' => $c ? '<a href="'.route('suppliers.statement', $c->id).'" class="btn btn-xs btn-light-primary"><i class="fas fa-file-alt"></i> Statement</a>' : '',
                    ];
                })->sortByDesc('due')->values();

                $totals = ['s_suppliers' => $rows->count()];
                foreach (['count', 'due', 'overdue', 'b30', 'b60', 'b90', 'b90p', 'balance'] as $k) {
                    $totals['s_'.$k] = $rows->sum($k);
                }

                return $this->json($rows, $totals);
            }

            $totals = (clone $base)->toBase()->selectRaw('COUNT(*) count, COALESCE(SUM(total),0) total, COALESCE(SUM(paid_amount),0) paid,
                COALESCE(SUM(returned_amount),0) returned, COALESCE(SUM(due_amount),0) due')->first();

            return DataTables::eloquent($base->with('supplier'))
                ->editColumn('date', fn ($p) => format_date($p->date))
                ->editColumn('due_date', fn ($p) => format_date($p->due_date))
                ->editColumn('purchase_no', fn ($p) => '<a href="'.route('purchases.show', $p->id).'">'.e($p->purchase_no).'</a>')
                ->addColumn('supplier_name', fn ($p) => e($p->supplier->display_name ?? '-'))
                ->filterColumn('supplier_name', fn ($q, $k) => $this->whereContact($q, 'supplier', $k))
                ->addColumn('days_overdue', fn ($p) => $this->overdueBadge($p->due_date))
                ->with('totals', $this->num((array) $totals))
                ->rawColumns(['purchase_no', 'days_overdue', 'supplier_name'])
                ->make(true);
        }

        return view('reports.payables', array_merge(['start' => $start, 'end' => $end, 'suppliers' => $this->suppliers()], ['labels' => $this->bucketLabels()]));
    }

    /* =====================================================================
     | 9. Expenses
     * =================================================================== */
    public function expenses(Request $request)
    {
        [$start, $end] = date_range_from_request($request);

        if ($request->ajax()) {
            $base = Expense::query()->whereBetween('expenses.date', [$start, $end])
                ->when($request->expense_category_id, fn ($q, $v) => $q->where('expenses.expense_category_id', $v))
                ->when($request->payment_method, fn ($q, $v) => $q->where('expenses.payment_method', $v));

            if ($request->section === 'categories') {
                $groups = (clone $base)->toBase()->groupBy('expense_category_id')
                    ->selectRaw('expense_category_id, COUNT(*) cnt, SUM(amount) amount')->get();
                $names = ExpenseCategory::withTrashed()->pluck('name', 'id');
                $grand = (float) $groups->sum('amount');
                $rows = $groups->map(fn ($g) => [
                    'category' => e($names[$g->expense_category_id] ?? '-'), 'count' => (int) $g->cnt,
                    'amount' => round((float) $g->amount, 2), 'percent' => $grand > 0 ? round($g->amount / $grand * 100, 1) : 0,
                ])->sortByDesc('amount')->values();

                return $this->json($rows, ['c_count' => $rows->sum('count'), 'c_amount' => $rows->sum('amount'), 'c_categories' => $rows->count()]);
            }

            if ($request->section === 'methods') {
                $groups = (clone $base)->toBase()->groupBy('payment_method')->selectRaw('payment_method, COUNT(*) cnt, SUM(amount) amount')->get();
                $grand = (float) $groups->sum('amount');
                $rows = $groups->map(fn ($g) => [
                    'method' => e(payment_method_label($g->payment_method)), 'count' => (int) $g->cnt,
                    'amount' => round((float) $g->amount, 2), 'percent' => $grand > 0 ? round($g->amount / $grand * 100, 1) : 0,
                ])->sortByDesc('amount')->values();

                return $this->json($rows, ['m_count' => $rows->sum('count'), 'm_amount' => $rows->sum('amount')]);
            }

            $totals = (clone $base)->toBase()->selectRaw('COUNT(*) count, COALESCE(SUM(amount),0) amount')->first();

            return DataTables::eloquent($base->with('category'))
                ->editColumn('date', fn ($x) => format_date($x->date))
                ->addColumn('category_name', fn ($x) => e($x->category->name ?? '-'))
                ->filterColumn('category_name', fn ($q, $k) => $q->whereHas('category', fn ($c) => $c->where('name', 'like', "%{$k}%")))
                ->editColumn('payment_method', fn ($x) => e(payment_method_label($x->payment_method)))
                ->with('totals', $this->num((array) $totals))
                ->rawColumns(['category_name', 'payment_method'])
                ->make(true);
        }

        return view('reports.expenses', [
            'start' => $start, 'end' => $end,
            'categories' => ExpenseCategory::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    /* =====================================================================
     | 10. Stock report / valuation
     * =================================================================== */
    public function stock(Request $request)
    {
        [$start, $end] = date_range_from_request($request);

        if ($request->ajax()) {
            if ($request->section === 'movements') {
                $rows = DB::table('products as p')
                    ->leftJoin('stock_movements as m', function ($j) use ($end) {
                        $j->on('m.product_id', '=', 'p.id')->where('m.date', '<=', $end);
                    })
                    ->whereNull('p.deleted_at')->where('p.track_stock', true)
                    ->when($request->category_id, fn ($q, $v) => $q->where('p.category_id', $v))
                    ->groupBy('p.id', 'p.name', 'p.sku')
                    ->selectRaw('p.id, p.name, p.sku,
                        COALESCE(SUM(CASE WHEN m.date < ? THEN m.quantity END),0) opening,
                        COALESCE(SUM(CASE WHEN m.date >= ? AND m.quantity > 0 THEN m.quantity END),0) qty_in,
                        COALESCE(SUM(CASE WHEN m.date >= ? AND m.quantity < 0 THEN m.quantity END),0) qty_out,
                        COALESCE(SUM(CASE WHEN m.date >= ? AND m.type = ? THEN -m.quantity END),0) sold', [$start, $start, $start, $start, 'sale'])
                    ->orderBy('p.name')->get()
                    ->map(fn ($r) => [
                        'product' => $this->productLink($r->id, $r->name), 'sku' => e($r->sku),
                        'opening' => (float) $r->opening, 'qty_in' => (float) $r->qty_in, 'qty_out' => abs((float) $r->qty_out),
                        'sold' => (float) $r->sold,
                        'closing' => round((float) $r->opening + (float) $r->qty_in + (float) $r->qty_out, 3),
                    ]);

                return $this->json($rows, [
                    'm_opening' => $rows->sum('opening'), 'm_in' => $rows->sum('qty_in'), 'm_out' => $rows->sum('qty_out'),
                    'm_sold' => $rows->sum('sold'), 'm_closing' => $rows->sum('closing'),
                ]);
            }

            $base = Product::query()->where('track_stock', true)
                ->when($request->category_id, fn ($q, $v) => $q->where('category_id', $v))
                ->when($request->stock_status === 'in', fn ($q) => $q->where('stock_quantity', '>', 0))
                ->when($request->stock_status === 'low', fn ($q) => $q->lowStock())
                ->when($request->stock_status === 'out', fn ($q) => $q->where('stock_quantity', '<=', 0));

            $totals = (clone $base)->toBase()->selectRaw('COUNT(*) items, COALESCE(SUM(stock_quantity),0) units,
                COALESCE(SUM(stock_quantity * cost_price),0) value_cost, COALESCE(SUM(stock_quantity * selling_price),0) value_retail,
                COALESCE(SUM(CASE WHEN stock_quantity <= alert_quantity THEN 1 ELSE 0 END),0) low_count')->first();
            $totals->potential_profit = $totals->value_retail - $totals->value_cost;

            return DataTables::eloquent($base->with(['category', 'unit']))
                ->editColumn('name', fn ($p) => $this->productLink($p->id, $p->name).($p->is_active ? '' : ' <span class="badge badge-secondary">Inactive</span>'))
                ->addColumn('category_name', fn ($p) => e($p->category->name ?? '-'))
                ->filterColumn('category_name', fn ($q, $k) => $q->whereHas('category', fn ($c) => $c->where('name', 'like', "%{$k}%")))
                ->addColumn('unit_name', fn ($p) => e($p->unit->short_name ?? ''))
                ->addColumn('value_cost', fn ($p) => round($p->stock_quantity * $p->cost_price, 2))
                ->addColumn('value_retail', fn ($p) => round($p->stock_quantity * $p->selling_price, 2))
                ->addColumn('potential_profit', fn ($p) => round($p->stock_quantity * ($p->selling_price - $p->cost_price), 2))
                ->addColumn('status', fn ($p) => $p->stock_quantity <= 0
                    ? '<span class="badge badge-danger">Out of stock</span>'
                    : ($p->is_low_stock ? '<span class="badge badge-warning">Low stock</span>' : '<span class="badge badge-success">In stock</span>'))
                ->with('totals', $this->num((array) $totals))
                ->rawColumns(['name', 'status', 'category_name', 'unit_name'])
                ->make(true);
        }

        return view('reports.stock', [
            'start' => $start, 'end' => $end, 'categories' => Category::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    /* =====================================================================
     | 11. Customer & supplier report
     * =================================================================== */
    public function contacts(Request $request)
    {
        [$start, $end] = date_range_from_request($request, 'all');

        if ($request->ajax()) {
            $type = $request->input('type', 'both');
            $rows = collect();
            $between = fn ($q, $col = 'date') => $q->whereBetween($col, [$start, $end]);

            if ($type !== 'suppliers') {
                $sales = $between(Sale::query())->toBase()->groupBy('customer_id')->selectRaw('customer_id id, SUM(total) v')->pluck('v', 'id');
                $returns = $between(SaleReturn::query())->toBase()->groupBy('customer_id')->selectRaw('customer_id id, SUM(total) v')->pluck('v', 'id');
                $paid = $between(Payment::where('party_type', 'customer'))->toBase()->groupBy('customer_id')->selectRaw('customer_id id, SUM(amount) v')->pluck('v', 'id');
                foreach (Customer::withBalance()->orderBy('name')->get() as $c) {
                    $rows->push([
                        'contact' => '<a href="'.route('customers.show', $c->id).'">'.e($c->display_name).'</a>',
                        'code' => e($c->code), 'type' => 'Customer',
                        'purchase' => 0, 'purchase_return' => 0,
                        'sale' => round((float) ($sales[$c->id] ?? 0), 2), 'sale_return' => round((float) ($returns[$c->id] ?? 0), 2),
                        'opening' => (float) $c->opening_balance, 'payments' => round((float) ($paid[$c->id] ?? 0), 2),
                        'balance' => round((float) $c->balance, 2),
                    ]);
                }
            }

            if ($type !== 'customers') {
                $purchases = $between(Purchase::query())->toBase()->groupBy('supplier_id')->selectRaw('supplier_id id, SUM(total) v')->pluck('v', 'id');
                $returns = $between(PurchaseReturn::query())->toBase()->groupBy('supplier_id')->selectRaw('supplier_id id, SUM(total) v')->pluck('v', 'id');
                $paid = $between(Payment::where('party_type', 'supplier'))->toBase()->groupBy('supplier_id')->selectRaw('supplier_id id, SUM(amount) v')->pluck('v', 'id');
                foreach (Supplier::withBalance()->orderBy('name')->get() as $s) {
                    $rows->push([
                        'contact' => '<a href="'.route('suppliers.show', $s->id).'">'.e($s->display_name).'</a>',
                        'code' => e($s->code), 'type' => 'Supplier',
                        'purchase' => round((float) ($purchases[$s->id] ?? 0), 2), 'purchase_return' => round((float) ($returns[$s->id] ?? 0), 2),
                        'sale' => 0, 'sale_return' => 0,
                        'opening' => (float) $s->opening_balance, 'payments' => round((float) ($paid[$s->id] ?? 0), 2),
                        'balance' => round((float) $s->balance, 2),
                    ]);
                }
            }

            $totals = [];
            foreach (['purchase', 'purchase_return', 'sale', 'sale_return', 'opening', 'payments'] as $k) {
                $totals[$k] = $rows->sum($k);
            }
            $totals['receivable'] = $rows->where('type', 'Customer')->sum('balance');
            $totals['payable'] = $rows->where('type', 'Supplier')->sum('balance');

            return $this->json($rows->values(), $totals);
        }

        return view('reports.contacts', ['start' => $start, 'end' => $end]);
    }

    /* =====================================================================
     | 12. Profit snapshot
     * =================================================================== */
    public function profit(Request $request)
    {
        [$start, $end] = date_range_from_request($request);

        if ($request->ajax()) {
            return match ($request->section) {
                'products' => $this->profitByProduct($start, $end),
                'monthly' => $this->profitByMonth($start, $end),
                default => $this->profitSummary($start, $end),
            };
        }

        return view('reports.profit', compact('start', 'end'));
    }

    protected function profitSummary(string $start, string $end): JsonResponse
    {
        $p = app(\App\Services\ProfitService::class)->summary($start, $end);
        $netSales = $p['net_sales'];
        $gross = $p['gross_profit'];

        $expenseRows = Expense::whereBetween('date', [$start, $end])->toBase()->groupBy('expense_category_id')
            ->selectRaw('expense_category_id, SUM(amount) amount')->get();
        $names = ExpenseCategory::withTrashed()->pluck('name', 'id');
        $expenses = $p['expenses'];
        $net = $p['net_profit'];
        $inputTax = $p['input_tax'];
        $outputTax = $p['output_tax'];
        $cogs = $p['cogs'];

        return response()->json([
            'totals' => $this->num([
                'invoice_count' => $p['invoice_count'],
                'gross_sales' => $p['gross_sales'], 'invoice_discount' => $p['invoice_discount'],
                'returns_net' => $p['returns_net'], 'net_sales' => $netSales, 'other_income' => $p['other_income'],
                'cogs' => $cogs, 'gross_profit' => $gross, 'expenses' => $expenses, 'net_profit' => $net,
                'output_tax' => $outputTax, 'input_tax' => $inputTax, 'net_tax' => $outputTax - $inputTax,
            ]) + [
                'gross_margin' => $netSales != 0 ? round($gross / $netSales * 100, 1).'%' : '0%',
                'net_margin' => $netSales != 0 ? round($net / $netSales * 100, 1).'%' : '0%',
            ],
            'expenses' => $expenseRows->map(fn ($e) => [
                'category' => e($names[$e->expense_category_id] ?? '-'), 'amount' => round((float) $e->amount, 2),
            ])->sortByDesc('amount')->values(),
        ]);
    }

    protected function profitByProduct(string $start, string $end): JsonResponse
    {
        // Line revenue scaled to each invoice's (subtotal - discount); see ProfitService::saleLines().
        $sold = app(\App\Services\ProfitService::class)->saleLines($start, $end)
            ->groupBy('si.product_id')
            ->selectRaw('si.product_id, SUM(si.quantity) qty,
                SUM(si.net_amount * ns.scale) revenue,
                SUM(si.quantity * si.unit_cost) cogs')->get()->keyBy('product_id');
        $returned = DB::table('sale_return_items as ri')->join('sale_returns as r', 'r.id', '=', 'ri.sale_return_id')
            ->whereNull('r.deleted_at')->whereBetween('r.date', [$start, $end])
            ->groupBy('ri.product_id')
            ->selectRaw('ri.product_id, SUM(ri.quantity) qty,
                SUM((ri.line_total - ri.tax_amount) - CASE WHEN r.subtotal > 0 THEN r.discount_amount * (ri.line_total - ri.tax_amount) / r.subtotal ELSE 0 END) revenue,
                SUM(ri.quantity * ri.unit_cost) cogs')->get()->keyBy('product_id');

        $ids = $sold->keys()->merge($returned->keys())->unique();
        $products = Product::withTrashed()->whereIn('id', $ids)->get(['id', 'name', 'sku'])->keyBy('id');

        $rows = $ids->map(function ($id) use ($sold, $returned, $products) {
            $a = $sold[$id] ?? null;
            $b = $returned[$id] ?? null;
            $revenue = (float) ($a->revenue ?? 0) - (float) ($b->revenue ?? 0);
            $cogs = (float) ($a->cogs ?? 0) - (float) ($b->cogs ?? 0);
            $p = $products[$id] ?? null;

            return [
                'product' => $p ? $this->productLink($p->id, $p->name) : '-', 'sku' => e($p->sku ?? ''),
                'qty' => round((float) ($a->qty ?? 0) - (float) ($b->qty ?? 0), 3),
                'revenue' => round($revenue, 2), 'cogs' => round($cogs, 2), 'profit' => round($revenue - $cogs, 2),
                'margin' => $revenue != 0 ? round(($revenue - $cogs) / $revenue * 100, 1) : 0,
            ];
        })->sortByDesc('profit')->values();

        return $this->json($rows, [
            'p_qty' => $rows->sum('qty'), 'p_revenue' => $rows->sum('revenue'), 'p_cogs' => $rows->sum('cogs'), 'p_profit' => $rows->sum('profit'),
        ]);
    }

    protected function profitByMonth(string $start, string $end): JsonResponse
    {
        $month = "DATE_FORMAT(date, '%Y-%m')";
        $sales = Sale::whereBetween('date', [$start, $end])->toBase()->groupByRaw($month)
            ->selectRaw("$month m, SUM(subtotal - discount_amount) v")->pluck('v', 'm');
        $charges = Sale::whereBetween('date', [$start, $end])->toBase()->groupByRaw($month)
            ->selectRaw("$month m, SUM(shipping_charges + additional_charges_total) v")->pluck('v', 'm');
        $returns = SaleReturn::whereBetween('date', [$start, $end])->toBase()->groupByRaw($month)
            ->selectRaw("$month m, SUM(subtotal - discount_amount) v")->pluck('v', 'm');
        $cogs = DB::table('sale_items as si')->join('sales as s', 's.id', '=', 'si.sale_id')
            ->whereNull('s.deleted_at')->whereBetween('s.date', [$start, $end])
            ->groupByRaw("DATE_FORMAT(s.date, '%Y-%m')")->selectRaw("DATE_FORMAT(s.date, '%Y-%m') m, SUM(si.quantity * si.unit_cost) v")->pluck('v', 'm');
        $cogsRet = DB::table('sale_return_items as ri')->join('sale_returns as r', 'r.id', '=', 'ri.sale_return_id')
            ->whereNull('r.deleted_at')->whereBetween('r.date', [$start, $end])
            ->groupByRaw("DATE_FORMAT(r.date, '%Y-%m')")->selectRaw("DATE_FORMAT(r.date, '%Y-%m') m, SUM(ri.quantity * ri.unit_cost) v")->pluck('v', 'm');
        $expenses = Expense::whereBetween('date', [$start, $end])->toBase()->groupByRaw($month)
            ->selectRaw("$month m, SUM(amount) v")->pluck('v', 'm');

        $rows = collect();
        $cursor = Carbon::parse($start)->startOfMonth();
        $last = Carbon::parse($end)->startOfMonth();
        while ($cursor->lte($last) && $rows->count() < 120) {
            $k = $cursor->format('Y-m');
            $net = (float) ($sales[$k] ?? 0) - (float) ($returns[$k] ?? 0);
            $c = (float) ($cogs[$k] ?? 0) - (float) ($cogsRet[$k] ?? 0);
            $e = (float) ($expenses[$k] ?? 0);
            $o = (float) ($charges[$k] ?? 0);
            $rows->push([
                'month' => ['display' => $cursor->format('M Y'), 'sort' => $k],
                'net_sales' => round($net, 2), 'other_income' => round($o, 2), 'cogs' => round($c, 2), 'gross_profit' => round($net + $o - $c, 2),
                'expenses' => round($e, 2), 'net_profit' => round($net + $o - $c - $e, 2),
            ]);
            $cursor->addMonth();
        }

        return $this->json($rows, [
            'm_net_sales' => $rows->sum('net_sales'), 'm_other_income' => $rows->sum('other_income'), 'm_cogs' => $rows->sum('cogs'), 'm_gross_profit' => $rows->sum('gross_profit'),
            'm_expenses' => $rows->sum('expenses'), 'm_net_profit' => $rows->sum('net_profit'),
        ]);
    }

    protected function salesCogs(string $start, string $end): float
    {
        return app(\App\Services\ProfitService::class)->salesCogs($start, $end);
    }

    protected function returnsCogs(string $start, string $end): float
    {
        return app(\App\Services\ProfitService::class)->returnsCogs($start, $end);
    }

    /* =====================================================================
     | Helpers
     * =================================================================== */

    /** JSON for client-side DataTables: {data: [...], totals: {...}} */
    protected function json(Collection $rows, array $totals = []): JsonResponse
    {
        return response()->json(['data' => $rows->values(), 'totals' => $this->num($totals)]);
    }

    protected function num(array $values): array
    {
        return array_map(fn ($v) => is_numeric($v) ? round((float) $v, 4) : $v, $values);
    }

    protected function productLink(int $id, string $name): string
    {
        return '<a href="'.route('products.show', $id).'">'.e($name).'</a>';
    }

    protected function whereContact(Builder $q, string $relation, string $keyword): void
    {
        $q->whereHas($relation, fn ($c) => $c->where(fn ($w) => $w->where('name', 'like', "%{$keyword}%")
            ->orWhere('company', 'like', "%{$keyword}%")->orWhere('code', 'like', "%{$keyword}%")));
    }

    protected function overdueBadge($dueDate): string
    {
        if (! $dueDate) {
            return '-';
        }
        $days = (int) floor($dueDate->copy()->startOfDay()->diffInDays(now()->startOfDay(), false));

        return $days > 0 ? '<span class="badge badge-danger">'.$days.' days</span>' : '<span class="badge badge-success">Not due</span>';
    }

    protected function bucket(int $days, bool $byDueDate): string
    {
        [$a, $b, $d] = $this->agingBoundaries();

        return match (true) {
            $byDueDate && $days <= 0 => 'current',
            $days <= $a => 'b30',
            $days <= $b => 'b60',
            $days <= $d => 'b90',
            default => 'b90p',
        };
    }

    protected function bucketLabels(): array
    {
        [$a, $b, $d] = $this->agingBoundaries();

        return [
            'current' => 'Current',
            'b30' => "0-{$a} days",
            'b60' => ($a + 1)."-{$b} days",
            'b90' => ($b + 1)."-{$d} days",
            'b90p' => "{$d}+ days",
        ];
    }

    /** @return array{0:int,1:int,2:int} */
    protected function agingBoundaries(): array
    {
        $b = array_values(array_map('intval', config('pos.aging_boundaries', [30, 60, 90])));
        sort($b);

        return [$b[0] ?? 30, $b[1] ?? 60, $b[2] ?? 90];
    }

    protected function customers()
    {
        return Customer::orderBy('name')->get(['id', 'name', 'company', 'code']);
    }

    protected function suppliers()
    {
        return Supplier::orderBy('name')->get(['id', 'name', 'company', 'code']);
    }
}
