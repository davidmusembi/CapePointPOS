<?php

namespace App\Models;

use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxRate extends Model
{
    use LogsModelActivity, SoftDeletes;

    protected $fillable = ['name', 'rate'];

    protected $casts = ['rate' => 'float'];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function getLabelAttribute(): string
    {
        return $this->name.' ('.(float) $this->rate.'%)';
    }
}
