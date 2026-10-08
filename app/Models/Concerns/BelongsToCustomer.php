<?php

namespace App\Models\Concerns;

use App\Models\Scopes\CustomerScope;
use App\Models\User;

trait BelongsToCustomer
{
    protected static function bootBelongsToCustomer(): void
    {
        static::addGlobalScope(new CustomerScope);

        static::creating(function ($model) {
            $user = auth()->user();
            if ($user && $user->role === 'customer' && empty($model->customer_id)) {
                $model->customer_id = $user->id;
            }
        });
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
