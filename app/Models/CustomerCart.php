<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerCart extends Model
{
    protected $fillable = ['user_id', 'store_id', 'lines'];

    protected $casts = [
        'lines' => 'array',
    ];
}
