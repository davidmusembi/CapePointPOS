<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Supplier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function __construct(
        protected StockService $stock,
        protected PaymentService $payments,
    ) {}

    /* ---------------------------------------------------------------------
     | Local Purchase Orders
     * ------------------------------------------------------------------- */

    public function saveOrder(?PurchaseOrder $order, array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $data) {
            $order ??= new PurchaseOrder(['lpo_no' => ReferenceService::next('lpo'), 'status' => 'pending']);
            if ($order->exists && $order->status !== 'pending') {
                throw ValidationException::withMessages(['lpo' => 'Only pending LPOs can be edited.']);
            }

            $totals = DocumentTotals::calculate($this->mapItems($data['items']), $data['discount_type'] ?? 'fixed', $data['discount_value'] ?? 0);

            $order->fill([
                'supplier_id' => $data['supplier_id'],
                'date' => $data['date'],
                'expected_date' => $data['expected_date'] ?? null,
                'delivery_address' => $data['delivery_address'] ?? null,
                'discount_type' => $data['discount_type'] ?? 'fixed',
                'discount_value' => $data['discount_value'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'discount_amount' => $totals['discount_amount'],
                'total' => $totals['total'],
            ])->save();

            $order->items()->delete();
            foreach ($totals['lines'] as $line) {
                $order->items()->create([
                    'product_id' => $line['product_id'],
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['price'],
                    'discount_percent' => $line['discount_percent'],
                    'discount_amount' => $line['discount_amount'],
                    'tax_rate' => $line['tax_rate'],
                    'tax_amount' => $line['tax_amount'],
                    'net_amount' => $line['net_amount'],
                    'line_total' => $line['line_total'],
                ]);
            }

            return $order;
        });
    }

    /* ---------------------------------------------------------------------
     | Purchase invoices / goods received
     * ------------------------------------------------------------------- */

    public function create(array $data): Purchase
    {
        return DB::transaction(function () use ($data) {
            $purchase = new Purchase(['purchase_no' => ReferenceService::next('purchase')]);
            $this->fill($purchase, $data);
            $purchase->refreshBalances();

            // Split payments entered on the purchase (each becomes a supplier payment allocated to it).
            $this->payments->recordDocumentPayments($purchase, $data);

            return $purchase->refreshBalances();
        });
    }

    public function update(Purchase $purchase, array $data): Purchase
    {
        if ($purchase->returns()->exists()) {
            throw ValidationException::withMessages(['purchase' => 'This purchase has returns and can no longer be edited.']);
        }

        return DB::transaction(function () use ($purchase, $data) {
            $this->revertReceipt($purchase);
            $this->fill($purchase, $data);

            // Payment rows edited on the purchase form (added / changed / removed)
            if (! empty($data['payments_present'])) {
                $this->payments->syncDocumentPayments($purchase, $data);
            }

            $allocated = round((float) $purchase->allocations()->sum('amount'), 2);
            if ($allocated - $purchase->total > 0.009) {
                throw ValidationException::withMessages(['items' => 'The purchase total ('.money($purchase->total).') cannot be less than the payments already allocated to it ('.money($allocated).'). Remove or reduce those payments first.']);
            }

            return $purchase->refreshBalances();
        });
    }

    public function delete(Purchase $purchase): void
    {
        if ($purchase->returns()->exists()) {
            throw ValidationException::withMessages(['purchase' => 'Delete the returns for this purchase first.']);
        }

        DB::transaction(function () use ($purchase) {
            // Items are kept on the (soft-deleted) purchase for audit history.
            $this->revertReceipt($purchase, true, false);
            $this->payments->detachFrom($purchase);
            $purchase->delete();
            activity('Purchase')->performedOn($purchase)->log("Purchase {$purchase->purchase_no} deleted");
        });
    }

    protected function revertReceipt(Purchase $purchase, bool $enforceStock = false, bool $deleteItems = true): void
    {
        $this->stock->reverseFor($purchase, $enforceStock);
        foreach ($purchase->items()->whereNotNull('purchase_order_item_id')->get() as $item) {
            PurchaseOrderItem::whereKey($item->purchase_order_item_id)->decrement('received_quantity', $item->quantity);
        }
        if ($deleteItems) {
            // Lines of previously deleted (soft-deleted) returns still reference these items.
            PurchaseReturnItem::whereIn('purchase_item_id', $purchase->items()->pluck('id'))->delete();
            $purchase->items()->delete();
        }
        $purchase->purchaseOrder?->refreshStatus();
    }

    protected function fill(Purchase $purchase, array $data): void
    {
        $supplier = Supplier::findOrFail($data['supplier_id']);
        $date = Carbon::parse($data['date']);
        $totals = DocumentTotals::calculate($this->mapItems($data['items']), $data['discount_type'] ?? 'fixed', $data['discount_value'] ?? 0);

        $order = ! empty($data['purchase_order_id']) ? PurchaseOrder::lockForUpdate()->find($data['purchase_order_id']) : null;
        if ($order && $order->supplier_id != $supplier->id) {
            throw ValidationException::withMessages(['supplier_id' => 'Supplier must match the selected LPO.']);
        }
        // New receipts need an open LPO; edits (already reverted above) only need it not to be cancelled.
        if ($order && ($order->status === 'cancelled' || (! $purchase->exists && ! $order->can_receive))) {
            throw ValidationException::withMessages(['purchase_order_id' => "LPO {$order->lpo_no} is {$order->status} and cannot receive goods."]);
        }
        // Received quantities may not exceed what is still pending on each LPO line.
        if ($order) {
            $requested = [];
            foreach ($totals['lines'] as $line) {
                if (! empty($line['purchase_order_item_id'])) {
                    $requested[(int) $line['purchase_order_item_id']] = ($requested[(int) $line['purchase_order_item_id']] ?? 0) + $line['quantity'];
                }
            }
            $orderItems = $order->items()->with('product')->whereIn('id', array_keys($requested))->lockForUpdate()->get()->keyBy('id');
            foreach ($requested as $itemId => $qty) {
                $oi = $orderItems[$itemId] ?? null;
                if (! $oi) {
                    throw ValidationException::withMessages(['items' => 'An item does not belong to the selected LPO.']);
                }
                if ($qty - $oi->pending_quantity > 0.0005) {
                    throw ValidationException::withMessages(['items' => ($oi->product->name ?? 'Item').': only '.qty_format($oi->pending_quantity).' pending on the LPO ('.qty_format($qty).' entered).']);
                }
            }
        }

        $terms = SaleService::resolveTerms($data['payment_terms'] ?? null, $supplier->payment_terms);

        // Shipping & additional charges: payable to the supplier on top of the taxable total.
        $shipping = round(max(0, (float) ($data['shipping_charges'] ?? 0)), 2);
        $charges = SaleService::normaliseCharges($data['additional_charges'] ?? []);
        $chargesTotal = round(array_sum(array_column($charges, 'amount')), 2);

        $purchase->fill([
            'shipping_details' => $data['shipping_details'] ?? null,
            'shipping_address' => $data['shipping_address'] ?? null,
            'shipping_charges' => $shipping,
            'shipping_status' => $data['shipping_status'] ?? null,
            'delivered_to' => $data['delivered_to'] ?? null,
            'delivery_person_id' => $data['delivery_person_id'] ?? null,
            'additional_charges' => $charges ?: null,
            'additional_charges_total' => $chargesTotal,
        ]);

        $purchase->fill([
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $order?->id,
            'supplier_invoice_no' => $data['supplier_invoice_no'] ?? null,
            'date' => $date->toDateString(),
            'payment_terms' => $terms,
            'due_date' => ! empty($data['due_date']) ? $data['due_date'] : $date->copy()->addDays($terms)->toDateString(),
            'discount_type' => $data['discount_type'] ?? 'fixed',
            'discount_value' => $data['discount_value'] ?? 0,
            'notes' => $data['notes'] ?? null,
            'subtotal' => $totals['subtotal'],
            'tax_amount' => $totals['tax_amount'],
            'discount_amount' => $totals['discount_amount'],
            'total' => round($totals['total'] + $shipping + $chargesTotal, 2),
        ]);
        $purchase->save();

        // Landed cost = line net (already incl. its share of the document discount) + its share of the
        // shipping / additional charges, spread over stock-tracked lines by value (last line absorbs rounding).
        $products = Product::withTrashed()->whereIn('id', array_column($totals['lines'], 'product_id'))->get()->keyBy('id');
        $capitalise = round($shipping + $chargesTotal, 2);
        $stockKeys = array_keys(array_filter($totals['lines'], fn ($l) => $products[$l['product_id']]->track_stock && $l['net_amount'] > 0));
        $stockNet = array_sum(array_map(fn ($k) => $totals['lines'][$k]['net_amount'], $stockKeys));
        $chargeShare = [];
        $allocated = 0.0;
        foreach ($stockKeys as $i => $k) {
            $chargeShare[$k] = $i === array_key_last($stockKeys)
                ? round($capitalise - $allocated, 2)
                : round($stockNet > 0 ? $capitalise * $totals['lines'][$k]['net_amount'] / $stockNet : 0, 2);
            $allocated += $chargeShare[$k];
        }

        foreach ($totals['lines'] as $key => $line) {
            $orderItemId = $order && ! empty($line['purchase_order_item_id'])
                ? $order->items()->whereKey($line['purchase_order_item_id'])->value('id')
                : null;

            $purchase->items()->create([
                'product_id' => $line['product_id'],
                'purchase_order_item_id' => $orderItemId,
                'quantity' => $line['quantity'],
                'unit_cost' => $line['price'],
                'discount_percent' => $line['discount_percent'],
                'discount_amount' => $line['discount_amount'],
                'tax_rate' => $line['tax_rate'],
                'tax_amount' => $line['tax_amount'],
                'net_amount' => $line['net_amount'],
                'line_total' => $line['line_total'],
            ]);

            if ($orderItemId) {
                PurchaseOrderItem::whereKey($orderItemId)->increment('received_quantity', $line['quantity']);
            }

            $landedCost = $line['quantity'] > 0 ? round(($line['net_amount'] + ($chargeShare[$key] ?? 0)) / $line['quantity'], 4) : 0;
            $this->stock->move($products[$line['product_id']], $line['quantity'], 'purchase', $purchase, $purchase->purchase_no, $purchase->date, $landedCost, null, true);
        }

        $order?->refreshStatus();
    }

    protected function mapItems(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'product_id' => (int) $row['product_id'],
                'purchase_order_item_id' => $row['purchase_order_item_id'] ?? null,
                'quantity' => $row['quantity'],
                'price' => $row['unit_cost'],
                'discount_percent' => $row['discount_percent'] ?? 0,
                'tax_rate' => $row['tax_rate'] ?? 0,
            ];
        }

        return $items;
    }

    /* ---------------------------------------------------------------------
     | Purchase returns (debit notes)
     * ------------------------------------------------------------------- */

    public function createReturn(Purchase $purchase, array $data): PurchaseReturn
    {
        return DB::transaction(function () use ($purchase, $data) {
            $purchase = Purchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            $purchaseItems = $purchase->items()->with('product')->get()->keyBy('id');
            $lines = [];
            $required = [];

            foreach ($data['items'] ?? [] as $itemId => $qty) {
                $qty = round((float) $qty, 3);
                if ($qty <= 0) {
                    continue;
                }
                $item = $purchaseItems[$itemId] ?? null;
                if (! $item) {
                    throw ValidationException::withMessages(['items' => 'Invalid purchase line selected.']);
                }
                if ($qty - $item->returnable_quantity > 0.0005) {
                    throw ValidationException::withMessages([
                        'items' => "{$item->product->name}: maximum returnable quantity is ".qty_format($item->returnable_quantity).'.',
                    ]);
                }
                $unitNet = $item->quantity > 0 ? $item->net_amount / $item->quantity : 0;
                $unitTax = $item->quantity > 0 ? $item->tax_amount / $item->quantity : 0;
                $isLast = abs($qty - $item->returnable_quantity) < 0.0005;
                $prev = $isLast ? SaleService::returnedSoFarFor($item->id, 'purchase') : null;
                $lines[] = [
                    'item' => $item, 'quantity' => $qty, 'unit_cost' => round($unitNet, 4),
                    // Returning the last remaining units debits exactly what is left on the line.
                    'net' => $isLast ? round($item->net_amount - $prev['net'], 2) : round($unitNet * $qty, 2),
                    'tax' => $isLast ? round($item->tax_amount - $prev['tax'], 2) : round($unitTax * $qty, 2),
                ];
                $required[$item->product_id] = ($required[$item->product_id] ?? 0) + $qty;
            }

            if (! $lines) {
                throw ValidationException::withMessages(['items' => 'Enter a return quantity for at least one item.']);
            }
            $this->stock->ensureAvailable($required);

            $subtotal = round(array_sum(array_column($lines, 'net')), 2);
            $tax = round(array_sum(array_column($lines, 'tax')), 2);
            $discount = round(($subtotal + $tax) * $purchase->returnDiscountFactor(), 2);
            $total = round($subtotal + $tax - $discount, 2);

            $refund = round((float) ($data['refund_amount'] ?? 0), 2);
            $maxRefund = round(max(0, $purchase->paid_amount - ($purchase->total - $purchase->returned_amount - $total)), 2);
            if ($refund - $maxRefund > 0.009) {
                throw ValidationException::withMessages(['refund_amount' => 'Maximum refundable amount is '.money($maxRefund).'.']);
            }

            $return = PurchaseReturn::create([
                'return_no' => ReferenceService::next('purchase_return'),
                'purchase_id' => $purchase->id,
                'supplier_id' => $purchase->supplier_id,
                'date' => $data['date'],
                'subtotal' => $subtotal,
                'tax_amount' => $tax,
                'discount_amount' => $discount,
                'total' => $total,
                'refund_amount' => $refund,
                'refund_method' => $refund > 0 ? ($data['refund_method'] ?? 'cash') : null,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                $item = $line['item'];
                $return->items()->create([
                    'purchase_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                    'tax_amount' => $line['tax'],
                    'line_total' => round($line['net'] + $line['tax'], 2),
                ]);
                $item->increment('returned_quantity', $line['quantity']);
                $this->stock->move($item->product_id, -$line['quantity'], 'purchase_return', $return, $return->return_no, $return->date, $line['unit_cost']);
            }

            $purchase->refreshBalances();

            return $return;
        });
    }

    public function deleteReturn(PurchaseReturn $return): void
    {
        DB::transaction(function () use ($return) {
            $this->stock->reverseFor($return);
            foreach ($return->items as $item) {
                $item->purchaseItem?->decrement('returned_quantity', $item->quantity);
            }
            $return->delete();
            $return->purchase?->refreshBalances();
            activity('PurchaseReturn')->performedOn($return)->log("Purchase return {$return->return_no} deleted");
        });
    }
}
