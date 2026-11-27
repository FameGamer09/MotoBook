<?php

namespace App\Services;

use App\Models\RiderSecurityDeposit;
use App\Models\RiderSecurityTopUp;
use Illuminate\Support\Facades\DB;

class RiderSecurityTopUpService
{
    public function approve(RiderSecurityTopUp $topUp, int $reviewerId): bool
    {
        return DB::transaction(function () use ($topUp, $reviewerId): bool {
            $lockedTopUp = RiderSecurityTopUp::query()->lockForUpdate()->findOrFail($topUp->id);

            if ($lockedTopUp->status !== 'pending') {
                return false;
            }

            $deposit = RiderSecurityDeposit::query()
                ->where('user_id', $lockedTopUp->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            $deposit->increment('balance', $lockedTopUp->amount);
            $deposit->refresh();
            $lockedTopUp->update([
                'status' => 'approved',
                'balance_after' => $deposit->balance,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
            ]);

            return true;
        });
    }

    public function reject(RiderSecurityTopUp $topUp, int $reviewerId): bool
    {
        return DB::transaction(function () use ($topUp, $reviewerId): bool {
            $lockedTopUp = RiderSecurityTopUp::query()->lockForUpdate()->findOrFail($topUp->id);

            if ($lockedTopUp->status !== 'pending') {
                return false;
            }

            $lockedTopUp->update([
                'status' => 'rejected',
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
            ]);

            return true;
        });
    }
}
