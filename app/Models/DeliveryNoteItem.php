<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryNoteItem extends Model
{
    protected $fillable = ['delivery_note_id', 'product_id', 'description', 'quantity'];

    protected $casts = ['quantity' => 'float'];

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
