<?php

namespace App\Models\Concerns;

use App\Models\PaymentAllocation;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Shared by Sale and Purchase: keeps paid / returned / due amounts and payment status in sync.
 *
 * paid     = allocated payments - refunds issued on returns
 * returned = sum of return (credit/debit note) totals
 * due      = total - returned - paid   (never below zero; an excess is a credit on the contact ledger)
 */
trait HasPaymentStatus
{
    public function allocations(): MorphMany
    {
        return $this->morphMany(PaymentAllocation::class, 'payable');
    }

    public function refreshBalances(): static
    {
        $allocated = (float) $this->allocations()->sum('amount');
        $returns = $this->returns()->get(['total', 'refund_amount']);

        $this->paid_amount = round($allocated - (float) $returns->sum('refund_amount'), 2);
        $this->returned_amount = round((float) $returns->sum('total'), 2);
        $this->due_amount = round(max(0, $this->total - $this->returned_amount - $this->paid_amount), 2);

        $this->payment_status = match (true) {
            $this->due_amount <= 0 => 'paid',
            $this->paid_amount > 0 => 'partial',
            default => 'due',
        };
        $this->saveQuietly();

        return $this;
    }

    /**
     * Discount share still to be applied on returns. 0 for documents whose line amounts already include
     * the document discount (current behaviour); >0 only for legacy post-tax-discount documents.
     */
    public function returnDiscountFactor(): float
    {
        return \App\Services\DocumentTotals::residualDiscountFactor((float) $this->items()->sum('line_total'), (float) $this->total);
    }

    public function getNetTotalAttribute(): float
    {
        return round($this->total - $this->returned_amount, 2);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->due_amount > 0 && $this->due_date && $this->due_date->isPast();
    }
}
