<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    use BelongsToStore, HasFactory;

    protected $fillable = [
        'store_id', 'menu_category_id', 'name', 'description', 'price', 'image', 'is_available', 'management_menu_item_id',
        'calories', 'prep_time_minutes', 'highlight_badge',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_available' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function optionGroups()
    {
        return $this->hasMany(MenuItemOptionGroup::class)->orderBy('sort_order');
    }
}
