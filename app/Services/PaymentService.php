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

            app(NotificationService::class)->paymentReceived($payment);

            return $payment;
        });
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
