<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasCreator, LogsModelActivity, SoftDeletes;

    protected $fillable = [
        'reference_no', 'date', 'expense_category_id', 'amount', 'payment_method',
        'payee', 'payment_reference', 'notes', 'created_by',
    ];

    protected $casts = ['date' => 'date', 'amount' => 'float'];

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id')->withTrashed();
    }
}
