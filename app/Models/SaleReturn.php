<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleReturn extends Model
{
    use HasCreator, LogsModelActivity, SoftDeletes;

    protected $fillable = [
        'return_no', 'sale_id', 'customer_id', 'date', 'subtotal', 'tax_amount', 'discount_amount', 'total',
        'refund_amount', 'refund_method', 'reason', 'notes', 'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'subtotal' => 'float',
        'tax_amount' => 'float',
        'discount_amount' => 'float',
        'total' => 'float',
        'refund_amount' => 'float',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class)->withTrashed();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function items()
    {
        return $this->hasMany(SaleReturnItem::class);
    }
}
