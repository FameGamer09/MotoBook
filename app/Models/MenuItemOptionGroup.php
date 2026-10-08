<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuItemOptionGroup extends Model
{
    protected $fillable = [
        'menu_item_id', 'name', 'selection_type', 'is_required', 'max_selections', 'sort_order', 'management_option_group_id',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function options()
    {
        return $this->hasMany(MenuItemOption::class)->orderBy('sort_order');
    }
}
