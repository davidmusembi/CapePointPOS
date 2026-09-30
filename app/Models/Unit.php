<?php

namespace App\Models;

use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use LogsModelActivity, SoftDeletes;

    protected $fillable = ['name', 'short_name', 'allow_decimal'];

    protected $casts = ['allow_decimal' => 'boolean'];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
