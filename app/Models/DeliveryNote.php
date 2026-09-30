<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryNote extends Model
{
    use HasCreator, LogsModelActivity, SoftDeletes;

    protected $fillable = [
        'delivery_no', 'sale_id', 'from_sale', 'customer_id', 'date', 'delivery_address', 'contact_person', 'contact_phone',
        'vehicle_no', 'driver_name', 'delivery_person_id', 'status', 'notes', 'created_by',
    ];

    protected $casts = ['date' => 'date', 'from_sale' => 'boolean'];

    protected static function booted(): void
    {
        // A note generated from an invoice's shipping section keeps the invoice's shipping status in step.
        static::saved(function (DeliveryNote $note) {
            if ($note->from_sale && $note->sale_id && $note->wasChanged('status')) {
                Sale::whereKey($note->sale_id)->where(fn ($q) => $q->whereNull('shipping_status')->orWhere('shipping_status', '!=', $note->status))
                    ->update(['shipping_status' => $note->status]);
            }
        });
    }

    /** Global shipping / delivery status list (config/pos.php). */
    public static function statuses(): array
    {
        return shipping_statuses();
    }

    public static function defaultStatus(): string
    {
        return array_key_first(config('pos.shipping_statuses', ['ordered' => []]));
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class)->withTrashed();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function deliveryPerson()
    {
        return $this->belongsTo(User::class, 'delivery_person_id')->withTrashed();
    }

    public function items()
    {
        return $this->hasMany(DeliveryNoteItem::class);
    }
}
