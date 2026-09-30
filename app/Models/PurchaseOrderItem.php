<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id', 'product_id', 'quantity', 'received_quantity', 'unit_cost', 'discount_percent', 'discount_amount',
        'tax_rate', 'tax_amount', 'net_amount', 'line_total',
    ];

    protected $casts = [
        'quantity' => 'float',
        'received_quantity' => 'float',
        'unit_cost' => 'float',
        'discount_percent' => 'float',
        'discount_amount' => 'float',
        'tax_rate' => 'float',
        'tax_amount' => 'float',
        'net_amount' => 'float',
        'line_total' => 'float',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function getPendingQuantityAttribute(): float
    {
        return max(0, round($this->quantity - $this->received_quantity, 3));
    }
}
