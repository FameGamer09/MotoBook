<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuCategory extends Model
{
    use BelongsToStore, HasFactory;

    protected $fillable = ['store_id', 'name', 'sort_order'];

    public function menuItems()
    {
        return $this->hasMany(MenuItem::class);
    }
}
