<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SaleReturn;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for sales revenue, COGS and profit figures (dashboard + reports).
 *
 * Net sales  = Σ sales (subtotal − invoice discount) − Σ returns (subtotal − discount)      [excl. tax]
 * COGS       = Σ sale lines qty × unit_cost − Σ returned lines qty × unit_cost              [returns by return date]
 * Gross      = Net sales − COGS
 * Net profit = Gross − expenses
 */
class ProfitService
{
    public function summary(string $start, string $end): array
    {
        $s = Sale::whereBetween('date', [$start, $end])->toBase()->selectRaw('COUNT(*) cnt, COALESCE(SUM(subtotal),0) subtotal,
            COALESCE(SUM(discount_amount),0) discount, COALESCE(SUM(tax_amount),0) tax, COALESCE(SUM(total),0) total')->first();
        $r = SaleReturn::whereBetween('date', [$start, $end])->toBase()->selectRaw('COUNT(*) cnt, COALESCE(SUM(subtotal),0) subtotal,
            COALESCE(SUM(discount_amount),0) discount, COALESCE(SUM(tax_amount),0) tax, COALESCE(SUM(total),0) total')->first();

        $salesNet = (float) $s->subtotal - (float) $s->discount;
        $returnsNet = (float) $r->subtotal - (float) $r->discount;
        $netSales = round($salesNet - $returnsNet, 2);
        $cogs = round($this->salesCogs($start, $end) - $this->returnsCogs($start, $end), 2);
        $expenses = round((float) Expense::whereBetween('date', [$start, $end])->sum('amount'), 2);
        $gross = round($netSales - $cogs, 2);

        $inputTax = (float) Purchase::whereBetween('date', [$start, $end])->sum('tax_amount')
            - (float) PurchaseReturn::whereBetween('date', [$start, $end])->sum('tax_amount');

        return [
            'invoice_count' => (int) $s->cnt,
            'gross_sales' => round((float) $s->subtotal, 2),
            'invoice_discount' => round((float) $s->discount, 2),
            'sales_total' => round((float) $s->total, 2),
            'returns_count' => (int) $r->cnt,
            'returns_net' => round($returnsNet, 2),
            'returns_total' => round((float) $r->total, 2),
            'net_sales' => $netSales,
            'cogs' => $cogs,
            'gross_profit' => $gross,
            'expenses' => $expenses,
            'net_profit' => round($gross - $expenses, 2),
            'output_tax' => round((float) $s->tax - (float) $r->tax, 2),
            'input_tax' => round($inputTax, 2),
        ];
    }

    public function salesCogs(string $start, string $end): float
    {
        return (float) DB::table('sale_items as si')->join('sales as s', 's.id', '=', 'si.sale_id')
            ->whereNull('s.deleted_at')->whereBetween('s.date', [$start, $end])->sum(DB::raw('si.quantity * si.unit_cost'));
    }

    public function returnsCogs(string $start, string $end): float
    {
        return (float) DB::table('sale_return_items as ri')->join('sale_returns as r', 'r.id', '=', 'ri.sale_return_id')
            ->whereNull('r.deleted_at')->whereBetween('r.date', [$start, $end])->sum(DB::raw('ri.quantity * ri.unit_cost'));
    }

    /**
     * Sale lines joined with their invoice and a per-invoice net sum, so each line's revenue can be scaled to
     * (subtotal − discount) of its invoice. For current invoices the scale is 1 (line nets already include the
     * pro-rated invoice discount); for legacy post-tax-discount invoices it spreads the discount correctly.
     *
     * Select `SUM(si.net_amount * ns.scale)` for revenue.
     */
    public function saleLines(string $start, string $end): Builder
    {
        $netSums = DB::table('sale_items')->groupBy('sale_id')->selectRaw('sale_id, SUM(net_amount) n');

        return DB::table('sale_items as si')->join('sales as s', 's.id', '=', 'si.sale_id')
            ->joinSub(
                DB::table('sales as s2')->joinSub($netSums, 'n', 'n.sale_id', '=', 's2.id')
                    ->selectRaw('s2.id sale_id, CASE WHEN n.n > 0 THEN (s2.subtotal - s2.discount_amount) / n.n ELSE 0 END scale'),
                'ns', 'ns.sale_id', '=', 's.id')
            ->whereNull('s.deleted_at')->whereBetween('s.date', [$start, $end]);
    }
}
