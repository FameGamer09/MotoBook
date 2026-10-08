<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'order_id', 'customer_id', 'store_id', 'rider_id',
        'store_rating', 'store_comment', 'rider_rating', 'rider_comment',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function rider()
    {
        return $this->belongsTo(Rider::class);
    }
}
