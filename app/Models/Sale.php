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

    public function deliveryPerson()
    {
        return $this->belongsTo(User::class, 'delivery_person_id')->withTrashed();
    }

    /** Delivery note generated and kept in sync by the invoice's shipping section. */
    public function shippingNote()
    {
        return $this->hasOne(DeliveryNote::class)->where('from_sale', true);
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** Shipping + additional charges billed on the invoice (outside the taxable subtotal). */
    public function getChargesTotalAttribute(): float
    {
        return round((float) $this->shipping_charges + (float) $this->additional_charges_total, 2);
    }

    public function getReferenceAttribute(): string
    {
        return $this->invoice_no;
    }
}
