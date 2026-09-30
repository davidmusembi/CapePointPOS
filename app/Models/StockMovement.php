<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasCreator;

    public const TYPES = [
        'opening' => 'Opening Stock',
        'purchase' => 'Purchase',
        'sale' => 'Sale',
        'sale_return' => 'Sales Return',
        'purchase_return' => 'Purchase Return',
        'adjustment' => 'Stock Adjustment',
    ];

    protected $fillable = [
        'product_id', 'type', 'quantity', 'unit_cost', 'balance_after', 'reference_type',
        'reference_id', 'reference_no', 'date', 'note', 'created_by',
    ];

    protected $casts = ['date' => 'date', 'quantity' => 'float', 'unit_cost' => 'float', 'balance_after' => 'float'];

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function reference()
    {
        return $this->morphTo();
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }
}
