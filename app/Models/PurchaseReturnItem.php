<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseReturnItem extends Model
{
    protected $fillable = [
        'purchase_return_id', 'purchase_item_id', 'product_id', 'quantity', 'unit_cost', 'tax_amount', 'line_total',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_cost' => 'float',
        'tax_amount' => 'float',
        'line_total' => 'float',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function purchaseItem()
    {
        return $this->belongsTo(PurchaseItem::class);
    }
}
