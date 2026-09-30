<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_id', 'product_id', 'purchase_order_item_id', 'quantity', 'unit_cost', 'discount_percent', 'discount_amount',
        'tax_rate', 'tax_amount', 'net_amount', 'line_total', 'returned_quantity',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_cost' => 'float',
        'discount_percent' => 'float',
        'discount_amount' => 'float',
        'tax_rate' => 'float',
        'tax_amount' => 'float',
        'net_amount' => 'float',
        'line_total' => 'float',
        'returned_quantity' => 'float',
    ];

    public function purchase()
    {
        return $this->belongsTo(Purchase::class)->withTrashed();
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
