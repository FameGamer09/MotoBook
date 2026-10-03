<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isRider(): bool
    {
        return $this->role === 'rider';
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

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function ordersAsCustomer()
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function ordersAsRider()
    {
        return $this->hasMany(Order::class, 'rider_id');
    }

    public function ordersAsMerchant()
    {
        return $this->hasMany(Order::class, 'merchant_id');
    }

    public function activeOrdersAsRider()
    {
        return $this->ordersAsRider()->active();
    }

    public function activeOrdersAsCustomer()
    {
        return $this->ordersAsCustomer()->active();
    }
}
