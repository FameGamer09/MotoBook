<?php

namespace App\Models\Concerns;

use App\Models\Rider;
use App\Models\Scopes\RiderScope;

trait BelongsToRider
{
    protected static function bootBelongsToRider(): void
    {
        static::addGlobalScope(new RiderScope);
    }

    public function rider()
    {
        return $this->belongsTo(Rider::class);
    }
}
