<?php

namespace App\Services;

/**
 * Line and document total calculations shared by sales invoices, LPOs and purchase invoices.
 *
 * Discounts are applied BEFORE tax so tax is charged on the amount actually billed:
 *   line gross        = qty * price
 *   line discount     = gross * line discount %
 *   subtotal          = sum(gross - line discount)                (before document discount)
 *   document discount = fixed amount (capped at subtotal) or % of subtotal,
 *                       allocated to lines pro-rata (last line absorbs rounding)
 *   line net          = gross - line discount - allocated document discount   (taxable amount)
 *   line tax          = line net * tax rate
 *   total             = subtotal - document discount + sum(line tax) = sum(line totals)
 *
 * The same algorithm is mirrored in public/js/doc-form.js - keep them in sync.
 */
class DocumentTotals
{
    /**
     * @param  array<int, array{quantity: mixed, price: mixed, discount_percent?: mixed, tax_rate?: mixed}>  $items
     * @return array{lines: array<int, array>, subtotal: float, tax_amount: float, discount_amount: float, total: float}
     */
    public static function calculate(array $items, string $discountType = 'fixed', $discountValue = 0): array
    {
        $p = self::precision();
        $lines = [];
        $subtotal = 0.0;

        foreach ($items as $key => $item) {
            $qty = round((float) $item['quantity'], 3);
            $price = round((float) $item['price'], 4);
            $discountPercent = min(100, max(0, (float) ($item['discount_percent'] ?? 0)));
            $taxRate = min(100, max(0, (float) ($item['tax_rate'] ?? 0)));

            $gross = round($qty * $price, $p);
            $lineDiscount = round($gross * $discountPercent / 100, $p);

            $lines[$key] = array_merge($item, [
                'quantity' => $qty,
                'price' => $price,
                'discount_percent' => $discountPercent,
                'tax_rate' => $taxRate,
                'gross' => $gross,
                'line_discount' => $lineDiscount,
                'after_line_discount' => round($gross - $lineDiscount, $p),
            ]);
            $subtotal += $gross - $lineDiscount;
        }
        $subtotal = round($subtotal, $p);

        $discountValue = max(0, (float) $discountValue);
        $documentDiscount = $discountType === 'percentage'
            ? round($subtotal * min(100, $discountValue) / 100, $p)
            : round(min($discountValue, $subtotal), $p);

        // Allocate the document discount pro-rata; the last non-zero line absorbs rounding.
        $allocated = 0.0;
        $keys = array_keys(array_filter($lines, fn ($l) => $l['after_line_discount'] > 0));
        $lastKey = end($keys);
        $tax = 0.0;

        foreach ($lines as $key => &$line) {
            $share = 0.0;
            if ($documentDiscount > 0 && $subtotal > 0 && $line['after_line_discount'] > 0) {
                $share = $key === $lastKey
                    ? round($documentDiscount - $allocated, $p)
                    : round($documentDiscount * $line['after_line_discount'] / $subtotal, $p);
                $allocated += $share;
            }
            $net = round($line['after_line_discount'] - $share, $p);
            $lineTax = round($net * $line['tax_rate'] / 100, $p);

            $line['document_discount'] = $share;
            $line['discount_amount'] = round($line['line_discount'] + $share, $p); // total discount on the line
            $line['net_amount'] = $net;
            $line['tax_amount'] = $lineTax;
            $line['line_total'] = round($net + $lineTax, $p);
            $tax += $lineTax;
        }
        unset($line);

        $tax = round($tax, $p);

        return [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'discount_amount' => $documentDiscount,
            'total' => round($subtotal - $documentDiscount + $tax, $p),
        ];
    }

    /** Money precision = business decimal places (0-2); amounts are stored with 2 decimals. */
    public static function precision(): int
    {
        return max(0, min(2, (int) settings('decimal_places', 2)));
    }

    /**
     * Portion of a document's discount that is NOT already inside its line amounts.
     * Documents created before discounts were applied pre-tax took the discount off the grand total;
     * for those the difference between sum(line totals) and the total must still be pro-rated on returns.
     * For current documents this is 0.
     */
    public static function residualDiscountFactor(float $sumLineTotals, float $documentTotal): float
    {
        $residual = round($sumLineTotals - $documentTotal, 2);

        return ($residual > 0 && $sumLineTotals > 0) ? $residual / $sumLineTotals : 0.0;
    }
}
