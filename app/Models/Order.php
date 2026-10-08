<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCustomer;
use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    use BelongsToCustomer, BelongsToStore, HasFactory;

    protected $fillable = [
        'order_number',
        'customer_id',
        'store_id',
        'rider_id',
        'delivery_address_id',
        'delivery_address_snapshot',
        'status',
        'payment_method',
        'payment_status',
        'subtotal',
        'delivery_fee',
        'service_fee',
        'discount_amount',
        'total_amount',
        'customer_note',
        'cancel_reason',
        'accepted_at',
        'ready_at',
        'picked_up_at',
        'delivered_at',
        'cancelled_at',
        'merchant_id',
        'merchant_lat',
        'merchant_lng',
        'pickup_address',
        'customer_lat',
        'customer_lng',
        'dropoff_address',
        'rider_lat',
        'rider_lng',
        'payment_reference',
        'delivery_notes',
        'customer_signature',
    ];

    protected function casts(): array
    {
        return [
            'delivery_address_snapshot' => 'array',
            'subtotal' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'service_fee' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'merchant_lat' => 'float',
            'merchant_lng' => 'float',
            'customer_lat' => 'float',
            'customer_lng' => 'float',
            'rider_lat' => 'float',
            'rider_lng' => 'float',
            'accepted_at' => 'datetime',
            'ready_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            if (empty($order->order_number)) {
                $order->order_number = static::generateOrderNumber();
            }
        });

        static::created(function (Order $order): void {
            DB::afterCommit(fn () => app(\App\Services\ManagementOrderSync::class)->push($order->fresh()));

            $customer = User::find($order->customer_id);
            if (($customer?->customer_settings['order_notifications'] ?? true) === false) {
                return;
            }
            CustomerNotification::create([
                'user_id' => $order->customer_id,
                'type' => 'order',
                'title' => 'Order placed',
                'message' => "Your order {$order->order_number} was sent to the restaurant.",
                'action_url' => route('customer.orders.show', $order, false),
            ]);
        });

        static::updated(function (Order $order): void {
            if (! $order->wasChanged('status')) {
                return;
            }
            DB::afterCommit(fn () => app(\App\Services\ManagementOrderSync::class)->push($order->fresh()));
            $customer = User::find($order->customer_id);
            if (($customer?->customer_settings['order_notifications'] ?? true) === false) {
                return;
            }

            $messages = [
                'accepted' => ['Order confirmed', 'The restaurant accepted your order and is preparing it.'],
                'preparing' => ['Order is being prepared', 'The restaurant has started preparing your order.'],
                'ready_for_pickup' => ['Order ready for pickup', 'Your order is ready and waiting for a rider.'],
                'assigned' => ['Rider assigned', 'A rider has been assigned to your order.'],
                'out_for_delivery' => ['Order picked up', 'Your rider is on the way to you.'],
                'delivered' => ['Order delivered', 'Your order has been delivered. Enjoy!'],
                'rejected' => ['Order declined', 'The restaurant could not accept your order.'],
                'cancelled' => ['Order cancelled', 'Your order was cancelled.'],
            ];

            if (! isset($messages[$order->status])) {
                return;
            }

            [$title, $message] = $messages[$order->status];
            CustomerNotification::create([
                'user_id' => $order->customer_id,
                'type' => 'order',
                'title' => $title,
                'message' => $message,
                'action_url' => route('customer.orders.show', $order, false),
            ]);
        });
    }

    public static function generateOrderNumber(): string
    {
        do {
            $number = 'MB-'.random_int(10000, 99999);
        } while (static::withoutGlobalScopes()->where('order_number', $number)->exists());

        return $number;
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function deliveryAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'delivery_address_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function promotionRedemptions(): HasMany
    {
        return $this->hasMany(PromotionRedemption::class);
    }

    public function hasRider(): bool
    {
        return $this->rider_id !== null;
    }

    public function hasMerchantLocation(): bool
    {
        return $this->merchant_lat !== null && $this->merchant_lng !== null;
    }

    public function hasRiderLocation(): bool
    {
        return $this->rider_lat !== null && $this->rider_lng !== null;
    }

    public function scopeForRider(Builder $query, int $riderId): Builder
    {
        return $query->where('rider_id', $riderId);
    }

    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            'pending',
            'accepted',
            'preparing',
            'ready_for_pickup',
            'assigned',
            'out_for_delivery',
        ]);
    }
}
