<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasCreator, LogsModelActivity, SoftDeletes;

    protected $fillable = [
        'name', 'sku', 'barcode', 'category_id', 'unit_id', 'tax_rate_id', 'cost_price', 'selling_price',
        'alert_quantity', 'track_stock', 'is_active', 'description', 'created_by',
    ];

    protected $casts = [
        'cost_price' => 'float',
        'selling_price' => 'float',
        'stock_quantity' => 'float',
        'alert_quantity' => 'float',
        'track_stock' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }

    public function taxRate()
    {
        return $this->belongsTo(TaxRate::class)->withTrashed();
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeLowStock(Builder $q): Builder
    {
        return $q->where('track_stock', true)->whereColumn('stock_quantity', '<=', 'alert_quantity');
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->track_stock && $this->stock_quantity <= $this->alert_quantity;
    }

    public function getTaxPercentAttribute(): float
    {
        return (float) ($this->taxRate->rate ?? 0);
    }
}
