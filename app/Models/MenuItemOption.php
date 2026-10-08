<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuItemOption extends Model
{
    protected $fillable = [
        'menu_item_option_group_id', 'name', 'price_delta', 'is_default', 'sort_order', 'management_option_id', 'is_available',
    ];

    protected $casts = [
        'price_delta' => 'decimal:2',
        'is_default' => 'boolean',
        'is_available' => 'boolean',
    ];

    public function group()
    {
        return $this->belongsTo(MenuItemOptionGroup::class, 'menu_item_option_group_id');
    }
}
