<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRider;
use Illuminate\Database\Eloquent\Model;

class RiderLocationLog extends Model
{
    use BelongsToRider;

    protected $fillable = ['rider_id', 'order_id', 'latitude', 'longitude', 'recorded_at'];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'recorded_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
