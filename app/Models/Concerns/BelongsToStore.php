<?php

namespace App\Models\Concerns;

use App\Models\Scopes\StoreScope;
use App\Models\Store;

trait BelongsToStore
{
    protected static function bootBelongsToStore(): void
    {
        static::addGlobalScope(new StoreScope);

        static::creating(function ($model) {
            $user = auth()->user();
            if ($user && $user->role === 'merchant' && $user->store && empty($model->store_id)) {
                $model->store_id = $user->store->id;
            }
        });
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
