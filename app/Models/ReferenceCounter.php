<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferenceCounter extends Model
{
    public $timestamps = false;

    protected $fillable = ['type', 'year', 'count'];
}
