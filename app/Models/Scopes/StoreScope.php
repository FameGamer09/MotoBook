<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts a query to the authenticated merchant's own store.
 * Applied to: MenuCategory, MenuItem, Order.
 * Admins are NOT affected (they must explicitly use withoutGlobalScope when needed).
 */
class StoreScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if ($user && $user->role === 'merchant' && $user->store) {
            $builder->where($model->getTable().'.store_id', $user->store->id);
        }
    }
}
