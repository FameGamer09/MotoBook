<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'slug', 'logo', 'banner', 'description', 'category', 'management_store_id',
        'phone', 'address', 'latitude', 'longitude', 'is_open', 'is_verified', 'brand_color',
        'rating', 'opening_hours', 'commission_rate', 'settings',
    ];

    protected $casts = [
        'is_open' => 'boolean',
        'is_verified' => 'boolean',
        'opening_hours' => 'array',
        'settings' => 'array',
        'rating' => 'decimal:1',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    // ---- Relationships ----

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function menuCategories()
    {
        return $this->hasMany(MenuCategory::class);
    }

    public function menuItems()
    {
        return $this->hasMany(MenuItem::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function promotions()
    {
        return $this->hasMany(Promotion::class);
    }
}
