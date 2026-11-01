<?php

namespace App\Models\Scopes;

use App\Models\Delivery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts a query to the authenticated rider's own records.
 * Applied to: Delivery, RiderLocationLog (via rider_id).
 */
class RiderScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if ($user && $user->role === 'rider' && $user->riderProfile) {
            $builder->where(function (Builder $query) use ($model, $user): void {
                $query->where($model->getTable().'.rider_id', $user->riderProfile->id);

                if ($model instanceof Delivery) {
                    $query->orWhere(function (Builder $offerQuery) use ($user): void {
                        $offerQuery->where('deliveries.status', 'pending_assignment')
                            ->whereHas('offers', fn (Builder $offers) => $offers
                                ->where('rider_id', $user->riderProfile->id)
                                ->whereIn('status', ['waiting', 'offered']));
                    });
                }
            });
        }
    }
}
