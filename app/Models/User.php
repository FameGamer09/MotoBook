<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'avatar',
        'status',
        'customer_settings',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'customer_settings' => 'array',
            'password' => 'hashed',
        ];
    }

    public function store(): HasOne
    {
        return $this->hasOne(Store::class);
    }

    public function riderProfile(): HasOne
    {
        return $this->hasOne(Rider::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function favoriteStores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'customer_favorites')->withTimestamps();
    }

    public function customerNotifications(): HasMany
    {
        return $this->hasMany(CustomerNotification::class);
    }

    public function customDeliveries(): HasMany
    {
        return $this->hasMany(CustomDelivery::class);
    }

    public function customerCart(): HasOne
    {
        return $this->hasOne(CustomerCart::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isRider(): bool
    {
        return $this->role === 'rider';
    }

    public function isMerchant(): bool
    {
        return $this->role === 'merchant';
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    public function isCashier(): bool
    {
        return $this->role === 'cashier';
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function ordersAsCustomer(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function ordersAsRider(): HasManyThrough
    {
        return $this->hasManyThrough(Order::class, Rider::class, 'user_id', 'rider_id');
    }

    public function ordersAsMerchant(): HasManyThrough
    {
        return $this->hasManyThrough(Order::class, Store::class, 'user_id', 'store_id');
    }

    public function activeOrdersAsRider(): Builder
    {
        return $this->ordersAsRider()->active();
    }

    public function activeOrdersAsCustomer(): Builder
    {
        return $this->ordersAsCustomer()->active();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
