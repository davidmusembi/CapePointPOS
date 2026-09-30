<?php

use App\Models\Setting;
use Illuminate\Support\Carbon;

if (! function_exists('settings')) {
    /**
     * Current business settings (cached single row).
     */
    function settings(?string $key = null, $default = null)
    {
        $setting = Setting::current();

        return $key === null ? $setting : ($setting->{$key} ?? $default);
    }
}

if (! function_exists('num_format')) {
    function num_format($value, ?int $decimals = null): string
    {
        $s = Setting::current();
        $decimals ??= (int) $s->decimal_places;

        return number_format((float) $value, $decimals, $s->decimal_separator ?: '.', $s->thousand_separator ?? ',');
    }
}

if (! function_exists('money')) {
    /**
     * Format a monetary value using the business currency settings.
     */
    function money($value, bool $withSymbol = true): string
    {
        $s = Setting::current();
        // round first so tiny negatives (e.g. -0.001) don't print as "-0.00"
        $value = round((float) $value, (int) $s->decimal_places);
        $formatted = num_format(abs($value));
        if ($withSymbol) {
            $formatted = $s->currency_position === 'after'
                ? $formatted.' '.$s->currency_symbol
                : $s->currency_symbol.' '.$formatted;
        }

        return ($value < 0 ? '-' : '').$formatted;
    }
}

if (! function_exists('qty_format')) {
    function qty_format($value): string
    {
        $value = (float) $value;

        return floor($value) == $value ? number_format($value, 0) : rtrim(rtrim(number_format($value, 3), '0'), '.');
    }
}

if (! function_exists('format_date')) {
    function format_date($date, bool $withTime = false): string
    {
        if (empty($date)) {
            return '';
        }
        $format = settings('date_format', 'd/m/Y').($withTime ? ' H:i' : '');

        return Carbon::parse($date)->format($format);
    }
}

if (! function_exists('payment_methods')) {
    /**
     * Payment methods enabled in business settings (catalogue in config/pos.php).
     * $withCredit adds the "pay later" option used on invoices / purchases.
     */
    function payment_methods(bool $withCredit = false, bool $onlyEnabled = true): array
    {
        $all = config('pos.payment_methods', []);
        $enabled = settings('enabled_payment_methods');
        $methods = $onlyEnabled && is_array($enabled) && $enabled
            ? array_intersect_key($all, array_flip($enabled))
            : $all;

        return $withCredit ? $methods + ['credit' => 'Credit (Pay Later)'] : $methods;
    }
}

if (! function_exists('payment_method_label')) {
    function payment_method_label(?string $method): string
    {
        return (config('pos.payment_methods', []) + ['credit' => 'Credit (Pay Later)'])[$method] ?? ucfirst(str_replace('_', ' ', (string) $method));
    }
}

if (! function_exists('payment_status_badge')) {
    function payment_status_badge(?string $status): string
    {
        $map = ['paid' => 'success', 'partial' => 'warning', 'due' => 'danger'];

        return '<span class="badge badge-pill badge-'.($map[$status] ?? 'secondary').'">'.e(ucfirst((string) $status)).'</span>';
    }
}

if (! function_exists('status_badge')) {
    function status_badge(?string $status): string
    {
        $map = [
            'pending' => 'secondary', 'partial' => 'warning', 'received' => 'success', 'cancelled' => 'dark',
            'dispatched' => 'info', 'delivered' => 'success', 'active' => 'success', 'inactive' => 'secondary',
            'ordered' => 'secondary', 'packed' => 'info', 'shipped' => 'primary',
        ];

        return '<span class="badge badge-pill badge-'.($map[$status] ?? 'secondary').'">'.e(ucfirst((string) $status)).'</span>';
    }
}

if (! function_exists('shipping_statuses')) {
    /** @return array<string, string> key => label (global list in config/pos.php) */
    function shipping_statuses(): array
    {
        return array_map(fn ($s) => $s['label'], config('pos.shipping_statuses', []));
    }
}

if (! function_exists('shipping_status_badge')) {
    function shipping_status_badge(?string $status): string
    {
        if (! $status) {
            return '<span class="text-muted">-</span>';
        }
        $s = config("pos.shipping_statuses.$status");

        return '<span class="badge badge-pill badge-'.e($s['tone'] ?? 'secondary').'">'.e($s['label'] ?? ucfirst($status)).'</span>';
    }
}

if (! function_exists('date_range_from_request')) {
    /**
     * Parse "start_date" / "end_date" (Y-m-d) from the request; defaults to the current month.
     *
     * @return array{0: string, 1: string}
     */
    function date_range_from_request(?\Illuminate\Http\Request $request = null, string $default = 'month'): array
    {
        $request ??= request();
        $start = $request->input('start_date');
        $end = $request->input('end_date');

        if (! $start || ! $end) {
            $start = match ($default) {
                'year' => now()->startOfYear()->toDateString(),
                'all' => '2000-01-01',
                'today' => now()->toDateString(),
                default => now()->startOfMonth()->toDateString(),
            };
            $end = now()->toDateString();
        }

        return [Carbon::parse($start)->toDateString(), Carbon::parse($end)->toDateString()];
    }
}

if (! function_exists('stock_adjustment_reasons')) {
    /** Reasons offered on stock adjustments (one per line in business settings, else config defaults). */
    function stock_adjustment_reasons(): array
    {
        $custom = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) settings('stock_adjustment_reasons')))));

        return $custom ?: config('pos.stock_adjustment_reasons', []);
    }
}