<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleReturnItem extends Model
{
    protected $fillable = [
        'sale_return_id', 'sale_item_id', 'product_id', 'quantity', 'unit_price', 'unit_cost', 'tax_amount', 'line_total',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'float',
        'unit_cost' => 'float',
        'tax_amount' => 'float',
        'line_total' => 'float',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function saleItem()
    {
        return $this->belongsTo(SaleItem::class);
    }
}
