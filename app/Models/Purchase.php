<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use App\Models\Concerns\HasPaymentStatus;
use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Purchase extends Model
{
    use HasCreator, HasPaymentStatus, LogsModelActivity, SoftDeletes;

    protected $fillable = [
        'purchase_no', 'supplier_invoice_no', 'purchase_order_id', 'supplier_id', 'date', 'due_date', 'payment_terms', 'subtotal',
        'tax_amount', 'discount_type', 'discount_value', 'discount_amount', 'total', 'notes', 'created_by',
        'shipping_details', 'shipping_address', 'shipping_charges', 'shipping_status', 'delivered_to',
        'delivery_person_id', 'additional_charges', 'additional_charges_total',
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
        'shipping_charges' => 'float',
        'additional_charges' => 'array',
        'additional_charges_total' => 'float',
    ];

    public function deliveryPerson()
    {
        return $this->belongsTo(User::class, 'delivery_person_id')->withTrashed();
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** Shipping + additional charges on the purchase (capitalised into stock cost). */
    public function getChargesTotalAttribute(): float
    {
        return round((float) $this->shipping_charges + (float) $this->additional_charges_total, 2);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class)->withTrashed();
    }

    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function returns()
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    public function payments()
    {
        return $this->belongsToMany(Payment::class, 'payment_allocations', 'payable_id', 'payment_id')
            ->wherePivot('payable_type', 'purchase')
            ->withPivot('amount')->withTimestamps();
    }

    public function getReferenceAttribute(): string
    {
        return $this->purchase_no;
    }
}
