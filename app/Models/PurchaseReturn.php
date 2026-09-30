<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReturn extends Model
{
    use HasCreator, LogsModelActivity, SoftDeletes;

    protected $fillable = [
        'return_no', 'purchase_id', 'supplier_id', 'date', 'subtotal', 'tax_amount', 'discount_amount', 'total',
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

    public function purchase()
    {
        return $this->belongsTo(Purchase::class)->withTrashed();
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function items()
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }
}
