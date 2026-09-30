<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\DeliveryNote;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(
        protected StockService $stock,
        protected PaymentService $payments,
    ) {}

    /**
     * Create a sales invoice, deduct stock and record the (optional) payment captured on the invoice.
     */
    public function create(array $data): Sale
    {
        return DB::transaction(function () use ($data) {
            $sale = new Sale(['invoice_no' => ReferenceService::next('invoice')]);
            $this->fill($sale, $data);
            $sale->refreshBalances();

            // Split payments entered on the invoice (each becomes a receipt allocated to it).
            $this->payments->recordDocumentPayments($sale, $data);

            return $sale->refreshBalances();
        });
    }

    public function update(Sale $sale, array $data): Sale
    {
        if ($sale->returns()->exists()) {
            throw ValidationException::withMessages(['sale' => 'This invoice has returns (credit notes) and can no longer be edited.']);
        }

        return DB::transaction(function () use ($sale, $data) {
            // Keep the original cost of goods for products already on the invoice (historical profit must not move).
            $originalCosts = $sale->items()->pluck('unit_cost', 'product_id')->map(fn ($c) => (float) $c)->all();

            $this->stock->reverseFor($sale);
            // Lines of voided (soft-deleted) credit notes still reference the old invoice lines.
            SaleReturnItem::whereIn('sale_item_id', $sale->items()->pluck('id'))->delete();
            $sale->items()->delete();
            $this->fill($sale, $data, $originalCosts);

            // Payment rows edited on the invoice form (added / changed / removed)
            if (! empty($data['payments_present'])) {
                $this->payments->syncDocumentPayments($sale, $data);
            }

            $allocated = round((float) $sale->allocations()->sum('amount'), 2);
            if ($allocated - $sale->total > 0.009) {
                throw ValidationException::withMessages(['items' => 'The invoice total ('.money($sale->total).') cannot be less than the payments already allocated to it ('.money($allocated).'). Remove or reduce those payments first.']);
            }

            return $sale->refreshBalances();
        });
    }

    public function delete(Sale $sale): void
    {
        if ($sale->returns()->exists()) {
            throw ValidationException::withMessages(['sale' => 'Delete the credit notes for this invoice first.']);
        }

        DB::transaction(function () use ($sale) {
            $this->stock->reverseFor($sale);
            $this->payments->detachFrom($sale);
            $sale->shippingNote()->first()?->delete();
            $sale->delete();
            activity('Sale')->performedOn($sale)->log("Invoice {$sale->invoice_no} deleted");
        });
    }

    /** Pay term in days: value entered on the document, else the contact's term, else the business default. */
    public static function resolveTerms($entered, $contactTerms): int
    {
        if ($entered !== null && $entered !== '') {
            return max(0, (int) $entered);
        }

        return (int) ($contactTerms ?? settings('default_payment_terms', 0));
    }

    /** @param  array<int, float>  $originalCosts  product_id => unit cost to keep when editing */
    protected function fill(Sale $sale, array $data, array $originalCosts = []): void
    {
        $customer = Customer::findOrFail($data['customer_id']);
        $date = Carbon::parse($data['date']);

        $items = [];
        foreach ($data['items'] as $row) {
            $items[] = [
                'product_id' => (int) $row['product_id'],
                'description' => $row['description'] ?? null,
                'quantity' => $row['quantity'],
                'price' => $row['unit_price'],
                'discount_percent' => $row['discount_percent'] ?? 0,
                'tax_rate' => $row['tax_rate'] ?? 0,
            ];
        }
        $totals = DocumentTotals::calculate($items, $data['discount_type'] ?? 'fixed', $data['discount_value'] ?? 0);

        $required = [];
        foreach ($totals['lines'] as $line) {
            $required[$line['product_id']] = ($required[$line['product_id']] ?? 0) + $line['quantity'];
        }
        $this->stock->ensureAvailable($required);

        $terms = self::resolveTerms($data['payment_terms'] ?? null, $customer->payment_terms);

        // Shipping & additional charges are billed on top of the taxable total (not discounted / taxed).
        $shipping = round(max(0, (float) ($data['shipping_charges'] ?? 0)), 2);
        $charges = self::normaliseCharges($data['additional_charges'] ?? []);
        $chargesTotal = round(array_sum(array_column($charges, 'amount')), 2);

        $sale->fill([
            'shipping_details' => $data['shipping_details'] ?? null,
            'shipping_address' => $data['shipping_address'] ?? null,
            'shipping_charges' => $shipping,
            'shipping_status' => $data['shipping_status'] ?? null,
            'delivered_to' => $data['delivered_to'] ?? null,
            'delivery_person_id' => $data['delivery_person_id'] ?? null,
            'additional_charges' => $charges ?: null,
            'additional_charges_total' => $chargesTotal,
        ]);

        $sale->fill([
            'customer_id' => $customer->id,
            'date' => $date->toDateString(),
            'payment_terms' => $terms,
            'due_date' => ! empty($data['due_date']) ? $data['due_date'] : $date->copy()->addDays($terms)->toDateString(),
            'customer_reference' => $data['customer_reference'] ?? null,
            'discount_type' => $data['discount_type'] ?? 'fixed',
            'discount_value' => $data['discount_value'] ?? 0,
            'notes' => $data['notes'] ?? null,
            'subtotal' => $totals['subtotal'],
            'tax_amount' => $totals['tax_amount'],
            'discount_amount' => $totals['discount_amount'],
            'total' => round($totals['total'] + $shipping + $chargesTotal, 2),
        ]);
        $sale->save();

        $products = Product::withTrashed()->whereIn('id', array_keys($required))->get()->keyBy('id');
        foreach ($totals['lines'] as $line) {
            $product = $products[$line['product_id']];
            $unitCost = $originalCosts[$product->id] ?? (float) $product->cost_price;
            $sale->items()->create([
                'product_id' => $product->id,
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['price'],
                'unit_cost' => $unitCost,
                'discount_percent' => $line['discount_percent'],
                'discount_amount' => $line['discount_amount'],
                'tax_rate' => $line['tax_rate'],
                'tax_amount' => $line['tax_amount'],
                'net_amount' => $line['net_amount'],
                'line_total' => $line['line_total'],
            ]);
            $this->stock->move($product, -$line['quantity'], 'sale', $sale, $sale->invoice_no, $sale->date, $unitCost);
        }

        $this->syncShippingNote($sale->fresh(['items', 'customer', 'deliveryPerson']));
    }

    /**
     * Keep the invoice's delivery note in step with its shipping section:
     * a shipping status creates / updates the note (items, address, recipient, delivery person, status);
     * clearing the status removes the auto-generated note.
     */
    public function syncShippingNote(Sale $sale): void
    {
        $note = $sale->shippingNote()->first();

        if (! $sale->shipping_status) {
            $note?->delete();

            return;
        }

        $customer = $sale->customer;
        $note ??= new DeliveryNote([
            'delivery_no' => ReferenceService::next('delivery_note'),
            'sale_id' => $sale->id,
            'from_sale' => true,
            'date' => $sale->date->toDateString(),
        ]);

        $note->fill([
            'customer_id' => $sale->customer_id,
            'delivery_address' => $sale->shipping_address ?: trim(collect([$customer->address, $customer->city])->filter()->implode(', ')) ?: null,
            'contact_person' => $sale->delivered_to ?: $customer->name,
            'contact_phone' => $customer->phone,
            'delivery_person_id' => $sale->delivery_person_id,
            'driver_name' => $sale->deliveryPerson?->name,
            'status' => $sale->shipping_status,
            'notes' => $sale->shipping_details,
        ])->save();

        $note->items()->delete();
        foreach ($sale->items as $item) {
            $note->items()->create([
                'product_id' => $item->product_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
            ]);
        }
    }

    /** Validation rules for the shipping section (shared by sales invoices and purchases). */
    public static function shippingRules(): array
    {
        return [
            'shipping_details' => ['nullable', 'string', 'max:2000'],
            'shipping_address' => ['nullable', 'string', 'max:1000'],
            'shipping_charges' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'shipping_status' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(shipping_statuses()))],
            'delivered_to' => ['nullable', 'string', 'max:190'],
            'delivery_person_id' => ['nullable', \Illuminate\Validation\Rule::exists('users', 'id')],
            'additional_charges' => ['nullable', 'array', 'max:20'],
            'additional_charges.*.name' => ['nullable', 'string', 'max:120'],
            'additional_charges.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'shipping_documents' => ['nullable', 'array', 'max:'.(int) config('pos.attachments.max_files', 10)],
            'shipping_documents.*' => AttachmentService::rules(),
        ];
    }

    public static function shippingMessages(): array
    {
        return [
            'shipping_documents.*.mimes' => 'Shipping documents must be one of: '.implode(', ', config('pos.attachments.mimes')).'.',
            'shipping_documents.*.max' => 'Each shipping document may not be larger than '.round(config('pos.attachments.max_kb') / 1024).' MB.',
        ];
    }

    /** Clean "additional expense" rows: keep rows with an amount; unnamed rows get a generic label. */
    public static function normaliseCharges($rows): array
    {
        $out = [];
        foreach ((array) $rows as $row) {
            $amount = round(max(0, (float) ($row['amount'] ?? 0)), 2);
            $name = trim((string) ($row['name'] ?? ''));
            if ($amount <= 0) {
                continue;
            }
            $out[] = ['name' => $name !== '' ? mb_substr($name, 0, 120) : 'Additional charge', 'amount' => $amount];
        }

        return $out;
    }

    /**
     * Create a sales return / credit note against an invoice, restock items and optionally refund.
     *
     * @param  array  $data  sale_id, date, reason, notes, refund_amount, refund_method, items[sale_item_id] => qty
     */
    public function createReturn(Sale $sale, array $data): SaleReturn
    {
        return DB::transaction(function () use ($sale, $data) {
            $sale = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();
            $saleItems = $sale->items()->get()->keyBy('id');
            $lines = [];

            foreach ($data['items'] ?? [] as $saleItemId => $qty) {
                $qty = round((float) $qty, 3);
                if ($qty <= 0) {
                    continue;
                }
                $item = $saleItems[$saleItemId] ?? null;
                if (! $item) {
                    throw ValidationException::withMessages(['items' => 'Invalid invoice line selected.']);
                }
                if ($qty - $item->returnable_quantity > 0.0005) {
                    throw ValidationException::withMessages([
                        'items' => "{$item->product->name}: maximum returnable quantity is ".qty_format($item->returnable_quantity).'.',
                    ]);
                }
                $unitNet = $item->quantity > 0 ? $item->net_amount / $item->quantity : 0;
                $unitTax = $item->quantity > 0 ? $item->tax_amount / $item->quantity : 0;
                $lines[] = [
                    'item' => $item,
                    'quantity' => $qty,
                    'unit_price' => round($unitNet, 2),
                    // Returning the last remaining units credits exactly what is left on the line (no 0.01 residue).
                    'net' => abs($qty - $item->returnable_quantity) < 0.0005
                        ? round($item->net_amount - $this->returnedSoFar($item->id, 'sale')['net'], 2)
                        : round($unitNet * $qty, 2),
                    'tax' => abs($qty - $item->returnable_quantity) < 0.0005
                        ? round($item->tax_amount - $this->returnedSoFar($item->id, 'sale')['tax'], 2)
                        : round($unitTax * $qty, 2),
                ];
            }

            if (! $lines) {
                throw ValidationException::withMessages(['items' => 'Enter a return quantity for at least one item.']);
            }

            $subtotal = round(array_sum(array_column($lines, 'net')), 2);
            $tax = round(array_sum(array_column($lines, 'tax')), 2);
            $discount = round(($subtotal + $tax) * $sale->returnDiscountFactor(), 2);
            $total = round($subtotal + $tax - $discount, 2);

            $refund = round((float) ($data['refund_amount'] ?? 0), 2);
            $maxRefund = round(max(0, $sale->paid_amount - ($sale->total - $sale->returned_amount - $total)), 2);
            if ($refund - $maxRefund > 0.009) {
                throw ValidationException::withMessages(['refund_amount' => 'Maximum refundable amount is '.money($maxRefund).'.']);
            }

            $return = SaleReturn::create([
                'return_no' => ReferenceService::next('credit_note'),
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
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
                    'sale_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'unit_cost' => $item->unit_cost,
                    'tax_amount' => $line['tax'],
                    'line_total' => round($line['net'] + $line['tax'], 2),
                ]);
                $item->increment('returned_quantity', $line['quantity']);
                $this->stock->move($item->product_id, $line['quantity'], 'sale_return', $return, $return->return_no, $return->date, $item->unit_cost);
            }

            $sale->refreshBalances();

            return $return;
        });
    }

    public function deleteReturn(SaleReturn $return): void
    {
        DB::transaction(function () use ($return) {
            $this->stock->reverseFor($return, true);
            foreach ($return->items as $item) {
                $item->saleItem?->decrement('returned_quantity', $item->quantity);
            }
            $return->delete();
            $return->sale?->refreshBalances();
            activity('SaleReturn')->performedOn($return)->log("Credit note {$return->return_no} deleted");
        });
    }
    /** Net / tax already credited on live (not deleted) returns for a document line. */
    public static function returnedSoFarFor(int $itemId, string $kind): array
    {
        $row = $kind === 'sale'
            ? \Illuminate\Support\Facades\DB::table('sale_return_items as ri')->join('sale_returns as r', 'r.id', '=', 'ri.sale_return_id')
                ->whereNull('r.deleted_at')->where('ri.sale_item_id', $itemId)
                ->selectRaw('COALESCE(SUM(ri.line_total - ri.tax_amount),0) net, COALESCE(SUM(ri.tax_amount),0) tax')->first()
            : \Illuminate\Support\Facades\DB::table('purchase_return_items as ri')->join('purchase_returns as r', 'r.id', '=', 'ri.purchase_return_id')
                ->whereNull('r.deleted_at')->where('ri.purchase_item_id', $itemId)
                ->selectRaw('COALESCE(SUM(ri.line_total - ri.tax_amount),0) net, COALESCE(SUM(ri.tax_amount),0) tax')->first();

        return ['net' => (float) $row->net, 'tax' => (float) $row->tax];
    }

    protected function returnedSoFar(int $itemId, string $kind): array
    {
        return self::returnedSoFarFor($itemId, $kind);
    }
}
