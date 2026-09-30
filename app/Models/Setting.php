<?php

namespace App\Models;

use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class Setting extends Model
{
    use LogsModelActivity;

    protected $guarded = ['id'];

    protected $casts = [
        'allow_negative_stock' => 'boolean',
        'decimal_places' => 'integer',
        'default_payment_terms' => 'integer',
        'default_alert_quantity' => 'float',
        'financial_year_start_month' => 'integer',
        'enabled_payment_methods' => 'array',
    ];

    protected static ?Setting $resolved = null;

    protected static function booted(): void
    {
        static::saved(fn () => static::flush());
    }

    public static function current(): Setting
    {
        if (static::$resolved) {
            return static::$resolved;
        }

        $setting = Cache::rememberForever('business_settings', function () {
            return Schema::hasTable('settings') ? static::query()->first() : null;
        });

        return static::$resolved = $setting ?? new static([
            'business_name' => config('app.name'),
            'currency_symbol' => 'KSh', 'currency_code' => 'KES', 'currency_position' => 'before',
            'decimal_places' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.',
            'date_format' => 'd/m/Y', 'tax_label' => 'VAT', 'default_payment_terms' => 30,
            'default_alert_quantity' => 5, 'allow_negative_stock' => false,
        ]);
    }

    public static function flush(): void
    {
        Cache::forget('business_settings');
        static::$resolved = null;
    }

    public function getActivitylogOptions(): \Spatie\Activitylog\LogOptions
    {
        return \Spatie\Activitylog\LogOptions::defaults()
            ->useLogName('Setting')->logUnguarded()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function defaultTaxRate()
    {
        return $this->belongsTo(TaxRate::class, 'default_tax_rate_id');
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? asset('storage/'.$this->logo) : null;
    }

    /** Absolute filesystem path of the logo, used when rendering PDFs. */
    public function getLogoPathAttribute(): ?string
    {
        $path = $this->logo ? storage_path('app/public/'.$this->logo) : null;

        return $path && is_file($path) ? $path : null;
    }
}
