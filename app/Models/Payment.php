<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasCreator, LogsModelActivity;

    protected $fillable = [
        'payment_no', 'party_type', 'customer_id', 'supplier_id', 'date', 'amount', 'method', 'reference',
        'notes', 'source', 'created_by',
    ];

    protected $casts = ['date' => 'date', 'amount' => 'float'];

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function getPartyAttribute(): Customer|Supplier|null
    {
        return $this->party_type === 'customer' ? $this->customer : $this->supplier;
    }

    public function getAllocatedAmountAttribute(): float
    {
        return (float) $this->allocations->sum('amount');
    }

    public function getUnallocatedAmountAttribute(): float
    {
        return round($this->amount - $this->allocated_amount, 2);
    }
}
