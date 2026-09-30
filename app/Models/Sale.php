<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use App\Models\Concerns\HasPaymentStatus;
use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use HasCreator, HasPaymentStatus, LogsModelActivity, SoftDeletes;

    protected $fillable = [
        'invoice_no', 'customer_id', 'date', 'due_date', 'payment_terms', 'customer_reference', 'subtotal', 'tax_amount',
        'discount_type', 'discount_value', 'discount_amount', 'total', 'notes', 'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'float',
        'tax_amount' => 'float',
        'discount_value' => 'float',
        'discount_amount' => 'float',
        'total' => 'float',
        'paid_amount' => 'float',
        'returned_amount' => 'float',
        'due_amount' => 'float',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function returns()
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function deliveryNotes()
    {
        return $this->hasMany(DeliveryNote::class);
    }

    public function payments()
    {
        return $this->belongsToMany(Payment::class, 'payment_allocations', 'payable_id', 'payment_id')
            ->wherePivot('payable_type', 'sale')
            ->withPivot('amount')->withTimestamps();
    }

    public function getReferenceAttribute(): string
    {
        return $this->invoice_no;
    }
}
