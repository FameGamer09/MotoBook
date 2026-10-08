<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts a query to the authenticated customer's own records.
 * Applied to: Order (via customer_id).
 */
class CustomerScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if ($user && $user->role === 'customer') {
            $builder->where($model->getTable().'.customer_id', $user->id);
        }
    }
}
