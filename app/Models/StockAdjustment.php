<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockAdjustment extends Model
{
    use HasCreator, LogsModelActivity, SoftDeletes;

    protected $fillable = ['reference_no', 'date', 'type', 'reason', 'total_amount', 'notes', 'created_by'];

    protected $casts = ['date' => 'date', 'total_amount' => 'float'];

    public function items()
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }
}
