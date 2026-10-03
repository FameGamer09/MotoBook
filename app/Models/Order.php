<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'customer_id',
        'rider_id',
        'merchant_id',
        'merchant_lat',
        'merchant_lng',
        'pickup_address',
        'customer_lat',
        'customer_lng',
        'dropoff_address',
        'rider_lat',
        'rider_lng',
        'total_amount',
        'delivery_fee',
        'payment_method',
        'payment_status',
        'payment_reference',
        'delivery_notes',
        'customer_signature',
        'status',
        'accepted_at',
        'picked_up_at',
        'delivered_at',
        'cancelled_at',
    ];

    protected $casts = [
        'merchant_lat' => 'float',
        'merchant_lng' => 'float',
        'customer_lat' => 'float',
        'customer_lng' => 'float',
        'rider_lat' => 'float',
        'rider_lng' => 'float',
        'total_amount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'accepted_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function hasRider(): bool
    {
        return !is_null($this->rider_id);
    }

    public function hasMerchantLocation(): bool
    {
        return !is_null($this->merchant_lat) && !is_null($this->merchant_lng);
    }

    public function hasRiderLocation(): bool
    {
        return !is_null($this->rider_lat) && !is_null($this->rider_lng);
    }

    public function scopeForRider($query, int $riderId)
    {
        return $query->where('rider_id', $riderId);
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            'OFFER_RECEIVED',
            'ACCEPTED',
            'NAVIGATING_TO_PICKUP',
            'ARRIVED_AT_PICKUP',
            'ORDER_VERIFIED',
            'NAVIGATING_TO_DROP_OFF',
            'ARRIVED_AT_DROP_OFF',
            'PROOF_SUBMITTED',
        ]);
    }
}
