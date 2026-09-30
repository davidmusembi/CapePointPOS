<?php

namespace App\Models;

use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseCategory extends Model
{
    use LogsModelActivity, SoftDeletes;

    protected $fillable = ['name', 'code', 'description'];

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
}
