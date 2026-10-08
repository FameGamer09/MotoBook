<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomDelivery extends Model
{
    protected $fillable = [
        'user_id', 'rider_id', 'pickup_address', 'pickup_landmark', 'sender_name', 'sender_phone',
        'pickup_latitude', 'pickup_longitude', 'dropoff_address', 'dropoff_landmark', 'recipient_name',
        'recipient_phone', 'dropoff_latitude', 'dropoff_longitude', 'package_category', 'description',
        'delivery_fee', 'service_fee', 'total_amount', 'payment_method', 'payment_status', 'status',
        'accepted_at', 'picked_up_at', 'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'pickup_latitude' => 'float', 'pickup_longitude' => 'float',
            'dropoff_latitude' => 'float', 'dropoff_longitude' => 'float',
            'delivery_fee' => 'decimal:2', 'service_fee' => 'decimal:2', 'total_amount' => 'decimal:2',
            'accepted_at' => 'datetime', 'picked_up_at' => 'datetime', 'delivered_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }
}
