<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Supplier;
use Illuminate\Support\Collection;

/**
 * Builds customer / supplier statements (running-balance ledgers).
 *
 * Customer: debit = invoices & refunds paid out, credit = receipts & credit notes. Balance > 0 => customer owes us.
 * Supplier: credit = purchases & refunds received, debit = payments & debit notes. Balance > 0 => we owe supplier.
 */
class LedgerService
{
    /**
     * @return array{opening: float, entries: Collection, closing: float, totals: array}
     */
    public function customerStatement(Customer $customer, string $from, string $to): array
    {
        $rows = collect();

        foreach (Sale::where('customer_id', $customer->id)->get() as $s) {
            $rows->push($this->row($s->date, 'Invoice', $s->invoice_no, 'Sales invoice', $s->total, 0, route('sales.show', $s)));
        }
        foreach (SaleReturn::where('customer_id', $customer->id)->get() as $r) {
            $rows->push($this->row($r->date, 'Credit Note', $r->return_no, 'Return on '.optional($r->sale)->invoice_no, 0, $r->total, route('sale-returns.show', $r)));
            if ($r->refund_amount > 0) {
                $rows->push($this->row($r->date, 'Refund', $r->return_no, 'Refund ('.payment_method_label($r->refund_method).')', $r->refund_amount, 0, route('sale-returns.show', $r)));
            }
        }
        foreach (Payment::where('party_type', 'customer')->where('customer_id', $customer->id)->get() as $p) {
            $rows->push($this->row($p->date, 'Receipt', $p->payment_no, payment_method_label($p->method).($p->reference ? ' - '.$p->reference : ''), 0, $p->amount, route('payments.show', $p)));
        }

        return $this->build($rows, (float) $customer->opening_balance, $from, $to, fn ($r) => $r['debit'] - $r['credit']);
    }

    public function supplierStatement(Supplier $supplier, string $from, string $to): array
    {
        $rows = collect();

        foreach (Purchase::where('supplier_id', $supplier->id)->get() as $p) {
            $rows->push($this->row($p->date, 'Purchase', $p->purchase_no, 'Supplier inv. '.($p->supplier_invoice_no ?: '-'), 0, $p->total, route('purchases.show', $p)));
        }
        foreach (PurchaseReturn::where('supplier_id', $supplier->id)->get() as $r) {
            $rows->push($this->row($r->date, 'Debit Note', $r->return_no, 'Return on '.optional($r->purchase)->purchase_no, $r->total, 0, route('purchase-returns.show', $r)));
            if ($r->refund_amount > 0) {
                $rows->push($this->row($r->date, 'Refund', $r->return_no, 'Refund received ('.payment_method_label($r->refund_method).')', 0, $r->refund_amount, route('purchase-returns.show', $r)));
            }
        }
        foreach (Payment::where('party_type', 'supplier')->where('supplier_id', $supplier->id)->get() as $p) {
            $rows->push($this->row($p->date, 'Payment', $p->payment_no, payment_method_label($p->method).($p->reference ? ' - '.$p->reference : ''), $p->amount, 0, route('payments.show', $p)));
        }

        return $this->build($rows, (float) $supplier->opening_balance, $from, $to, fn ($r) => $r['credit'] - $r['debit']);
    }

    protected function row($date, string $type, string $ref, string $description, float $debit, float $credit, ?string $url = null): array
    {
        return [
            'date' => $date->toDateString(),
            'type' => $type,
            'reference' => $ref,
            'description' => $description,
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
            'url' => $url,
        ];
    }

    protected function build(Collection $rows, float $openingBalance, string $from, string $to, callable $effect): array
    {
        // Same-day order: document first, then its credit/debit note, refunds, then payments.
        $priority = ['Invoice' => 1, 'Purchase' => 1, 'Credit Note' => 2, 'Debit Note' => 2, 'Refund' => 3, 'Receipt' => 4, 'Payment' => 4];
        $rows = $rows->map(fn ($r) => $r + ['_p' => $priority[$r['type']] ?? 9])
            ->sortBy([['date', 'asc'], ['_p', 'asc'], ['reference', 'asc']])->values();

        $opening = $openingBalance + $rows->filter(fn ($r) => $r['date'] < $from)->sum($effect);
        $balance = $opening;

        $entries = $rows->filter(fn ($r) => $r['date'] >= $from && $r['date'] <= $to)->map(function ($r) use (&$balance, $effect) {
            $balance += $effect($r);
            $r['balance'] = round($balance, 2);

            return $r;
        })->values();

        return [
            'opening' => round($opening, 2),
            'entries' => $entries,
            'closing' => round($balance, 2),
            'totals' => ['debit' => round($entries->sum('debit'), 2), 'credit' => round($entries->sum('credit'), 2)],
        ];
    }
}
