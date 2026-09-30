<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id', 'product_id', 'description', 'quantity', 'unit_price', 'unit_cost', 'discount_percent', 'discount_amount',
        'tax_rate', 'tax_amount', 'net_amount', 'line_total', 'returned_quantity',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'float',
        'unit_cost' => 'float',
        'discount_percent' => 'float',
        'discount_amount' => 'float',
        'tax_rate' => 'float',
        'tax_amount' => 'float',
        'net_amount' => 'float',
        'line_total' => 'float',
        'returned_quantity' => 'float',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class)->withTrashed();
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function getReturnableQuantityAttribute(): float
    {
        return max(0, round($this->quantity - $this->returned_quantity, 3));
    }
}
