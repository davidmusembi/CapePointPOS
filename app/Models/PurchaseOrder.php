<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasCreator, LogsModelActivity, SoftDeletes;

    protected $fillable = [
        'lpo_no', 'supplier_id', 'date', 'expected_date', 'subtotal', 'tax_amount', 'discount_type',
        'discount_value', 'discount_amount', 'total', 'status', 'delivery_address', 'notes', 'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'expected_date' => 'date',
        'subtotal' => 'float',
        'tax_amount' => 'float',
        'discount_value' => 'float',
        'discount_amount' => 'float',
        'total' => 'float',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    /** Recompute receiving status from item received quantities. */
    public function refreshStatus(): void
    {
        if ($this->status === 'cancelled') {
            return;
        }
        $items = $this->items()->get();
        $ordered = $items->sum('quantity');
        $received = $items->sum(fn ($i) => min($i->received_quantity, $i->quantity));

        $this->status = match (true) {
            $received <= 0 => 'pending',
            $received + 0.0001 >= $ordered => 'received',
            default => 'partial',
        };
        $this->saveQuietly();
    }

    public function getCanReceiveAttribute(): bool
    {
        return in_array($this->status, ['pending', 'partial'], true);
    }
}
