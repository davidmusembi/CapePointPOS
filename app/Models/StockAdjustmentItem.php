<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAdjustmentItem extends Model
{
    protected $fillable = ['stock_adjustment_id', 'product_id', 'quantity', 'unit_cost', 'subtotal'];

    protected $casts = ['quantity' => 'float', 'unit_cost' => 'float', 'subtotal' => 'float'];

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
