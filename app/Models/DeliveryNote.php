<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryNote extends Model
{
    use HasCreator, LogsModelActivity, SoftDeletes;

    public const STATUSES = ['pending' => 'Pending', 'dispatched' => 'Dispatched', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];

    protected $fillable = [
        'delivery_no', 'sale_id', 'customer_id', 'date', 'delivery_address', 'contact_person', 'contact_phone',
        'vehicle_no', 'driver_name', 'status', 'notes', 'created_by',
    ];

    protected $casts = ['date' => 'date'];

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
        return $this->hasMany(DeliveryNoteItem::class);
    }
}
