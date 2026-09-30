<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentAllocation extends Model
{
    protected $fillable = ['payment_id', 'payable_type', 'payable_id', 'amount'];

    protected $casts = ['amount' => 'float'];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function payable()
    {
        return $this->morphTo()->withTrashed();
    }
}
