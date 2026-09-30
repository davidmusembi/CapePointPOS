<?php

namespace App\Services;

use App\Models\ReferenceCounter;
use Illuminate\Support\Facades\DB;

/**
 * Generates sequential document numbers such as INV2026-00012 using the prefixes in business settings.
 */
class ReferenceService
{
    /** type => settings column holding the prefix */
    protected const PREFIX_COLUMNS = [
        'invoice' => 'invoice_prefix',
        'credit_note' => 'credit_note_prefix',
        'delivery_note' => 'delivery_note_prefix',
        'lpo' => 'lpo_prefix',
        'purchase' => 'purchase_prefix',
        'purchase_return' => 'purchase_return_prefix',
        'customer_payment' => 'customer_payment_prefix',
        'supplier_payment' => 'supplier_payment_prefix',
        'expense' => 'expense_prefix',
        'adjustment' => 'adjustment_prefix',
    ];

    /** Contact codes are not year-based. */
    protected const CONTACT_PREFIXES = ['customer' => 'CUS', 'supplier' => 'SUP'];

    public static function next(string $type): string
    {
        if (isset(self::CONTACT_PREFIXES[$type])) {
            return sprintf('%s-%04d', self::CONTACT_PREFIXES[$type], self::increment($type, 0));
        }

        $column = self::PREFIX_COLUMNS[$type] ?? null;
        $prefix = $column ? (settings($column) ?: strtoupper($type)) : strtoupper($type);
        $year = (int) now()->format('Y');

        return sprintf('%s%d-%05d', $prefix, $year, self::increment($type, $year));
    }

    protected static function increment(string $type, int $year): int
    {
        return DB::transaction(function () use ($type, $year) {
            // insertOrIgnore is atomic on the unique (type, year) index, so concurrent first requests can't collide.
            ReferenceCounter::query()->insertOrIgnore(['type' => $type, 'year' => $year, 'count' => 0]);
            $counter = ReferenceCounter::where('type', $type)->where('year', $year)->lockForUpdate()->first();
            $counter->count++;
            $counter->save();

            return $counter->count;
        });
    }
}
