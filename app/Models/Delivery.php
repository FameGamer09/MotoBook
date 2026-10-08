<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRider;
use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    use BelongsToRider; // riders only ever see their own assigned deliveries

    protected $fillable = [
        'order_id', 'rider_id', 'status', 'assigned_at', 'accepted_at', 'rejected_at',
        'picked_up_at', 'delivered_at', 'distance_km', 'eta_minutes', 'proof_photo',
        'delivery_otp', 'base_fare', 'incentive', 'tip', 'total_earning',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
        'distance_km' => 'decimal:2',
        'base_fare' => 'decimal:2',
        'incentive' => 'decimal:2',
        'tip' => 'decimal:2',
        'total_earning' => 'decimal:2',
    ];

    // note: rider() relationship is provided by BelongsToRider

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
