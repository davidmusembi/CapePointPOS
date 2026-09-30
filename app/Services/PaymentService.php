<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    /**
     * Record a customer receipt or supplier payment and allocate it to invoices.
     *
     * @param  array  $data  party_type, customer_id|supplier_id, date, amount, method, reference, notes, source
     * @param  array<int, float>  $allocations  sale_id|purchase_id => amount
     */
    public function record(array $data, array $allocations = []): Payment
    {
        return DB::transaction(function () use ($data, $allocations) {
            $isCustomer = $data['party_type'] === 'customer';
            $partyColumn = $isCustomer ? 'customer_id' : 'supplier_id';
            $payableClass = $isCustomer ? Sale::class : Purchase::class;

            $allocations = array_filter(array_map(fn ($v) => round((float) $v, 2), $allocations), fn ($v) => $v > 0);
            $amount = round((float) $data['amount'], 2);

            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Payment amount must be greater than zero.']);
            }
            if (array_sum($allocations) - $amount > 0.009) {
                throw ValidationException::withMessages(['amount' => 'Allocated amount ('.money(array_sum($allocations)).') exceeds the payment amount.']);
            }

            $payables = $payableClass::whereIn('id', array_keys($allocations))
                ->where($partyColumn, $data[$partyColumn])->lockForUpdate()->get()->keyBy('id');

            foreach ($allocations as $id => $value) {
                $payable = $payables[$id] ?? null;
                if (! $payable) {
                    throw ValidationException::withMessages(['allocations' => 'Invalid invoice selected for allocation.']);
                }
                if ($value - $payable->due_amount > 0.009) {
                    throw ValidationException::withMessages([
                        'allocations' => "Allocation for {$payable->reference} (".money($value).') exceeds its balance due of '.money($payable->due_amount).'.',
                    ]);
                }
            }

            $payment = Payment::create([
                'payment_no' => ReferenceService::next($isCustomer ? 'customer_payment' : 'supplier_payment'),
                'party_type' => $data['party_type'],
                'customer_id' => $isCustomer ? $data['customer_id'] : null,
                'supplier_id' => $isCustomer ? null : $data['supplier_id'],
                'date' => $data['date'],
                'amount' => $amount,
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'source' => $data['source'] ?? 'manual',
            ]);

            foreach ($allocations as $id => $value) {
                $payment->allocations()->create([
                    'payable_type' => $payables[$id]->getMorphClass(),
                    'payable_id' => $id,
                    'amount' => $value,
                ]);
                $payables[$id]->refreshBalances();
            }

            return $payment;
        });
    }

    /** Validation rules for the split-payment rows on invoice / purchase forms. */
    public static function rowRules(): array
    {
        return [
            'payments' => ['nullable', 'array', 'max:10'],
            'payments.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'payments.*.paid_on' => ['nullable', 'date', 'before_or_equal:'.now()->addDay()->toDateString()],
            'payments.*.method' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(payment_methods()))],
            'payments.*.reference' => ['nullable', 'string', 'max:190'],
            'payments.*.note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Payment rows captured on a document form. Accepts the split rows (payments[]) or the legacy single
     * payment fields (payment_method / payment_amount / payment_reference). Rows without an amount are ignored.
     *
     * @return array<int, array{amount: float, paid_on: ?string, method: string, reference: ?string, note: ?string}>
     */
    public static function rowsFrom(array $data): array
    {
        $rows = $data['payments'] ?? null;
        if (! is_array($rows)) {
            $method = $data['payment_method'] ?? 'credit';
            $rows = $method === 'credit' ? [] : [[
                'amount' => $data['payment_amount'] ?? 0, 'method' => $method, 'reference' => $data['payment_reference'] ?? null,
            ]];
        }

        return collect($rows)
            ->map(fn ($r) => [
                'amount' => round(max(0, (float) ($r['amount'] ?? 0)), 2),
                'paid_on' => $r['paid_on'] ?? null,
                'method' => (string) ($r['method'] ?? array_key_first(payment_methods())),
                'reference' => $r['reference'] ?? null,
                'note' => $r['note'] ?? null,
            ])
            ->filter(fn ($r) => $r['amount'] > 0)
            ->values()->all();
    }

    /**
     * Record the payment rows entered on a new invoice / purchase, each as its own receipt allocated to the document.
     */
    public function recordDocumentPayments(Sale|Purchase $document, array $data): void
    {
        $rows = self::rowsFrom($data);
        if (! $rows) {
            return;
        }

        $isSale = $document instanceof Sale;
        $paying = round(array_sum(array_column($rows, 'amount')), 2);
        if ($paying - $document->due_amount > 0.009) {
            throw ValidationException::withMessages(['payments' => 'Total paid ('.money($paying).') cannot exceed the '
                .($isSale ? 'invoice' : 'purchase').' total of '.money($document->due_amount).'.']);
        }

        foreach ($rows as $row) {
            $this->record([
                'party_type' => $isSale ? 'customer' : 'supplier',
                'customer_id' => $isSale ? $document->customer_id : null,
                'supplier_id' => $isSale ? null : $document->supplier_id,
                'date' => $row['paid_on'] ?: $document->date->toDateString(),
                'amount' => $row['amount'],
                'method' => $row['method'],
                'reference' => $row['reference'],
                'notes' => $row['note'] ?: 'Payment on '.($isSale ? 'invoice ' : 'purchase ').$document->reference,
                'source' => 'invoice',
            ], [$document->id => $row['amount']]);
            $document->refresh();
        }
    }

    public function delete(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $payables = $payment->allocations()->with('payable')->get()->pluck('payable')->filter();
            $payment->allocations()->delete();
            $payment->delete();
            $payables->each->refreshBalances();
        });
    }

    /**
     * Remove allocations pointing at a document being voided. Payments captured on the invoice itself
     * are removed with it; independent receipts stay on the account as unallocated credit.
     */
    public function detachFrom(Sale|Purchase $payable): void
    {
        $allocations = $payable->allocations()->with('payment')->get();
        foreach ($allocations as $allocation) {
            $payment = $allocation->payment;
            $allocation->delete();
            if ($payment && $payment->source === 'invoice' && ! $payment->allocations()->exists()) {
                $payment->delete();
            }
        }
    }
}
