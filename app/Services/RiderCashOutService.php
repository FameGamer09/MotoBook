<?php

namespace App\Services;

use App\Models\RiderCashOutRequest as RiderCashOut;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RiderCashOutService
{
    /**
     * @param  array{amount: numeric-string|int|float, account_name: string, account_number: string}  $data
     */
    public function request(User $user, array $data): RiderCashOut
    {
        return DB::transaction(function () use ($user, $data): RiderCashOut {
            $wallet = Wallet::query()->where('user_id', $user->id)->lockForUpdate()->first();
            $amount = (float) $data['amount'];

            if (! $wallet || (float) $wallet->balance < $amount) {
                throw ValidationException::withMessages([
                    'amount' => 'Your earnings wallet does not have enough available balance.',
                ]);
            }

            $cashOut = $user->riderCashOutRequests()->create([
                'amount' => $amount,
                'payout_method' => 'gcash',
                'account_name' => $data['account_name'],
                'account_number' => $data['account_number'],
            ]);

            $wallet->debit($amount, "Cash-out request #{$cashOut->id}", $cashOut);

            return $cashOut;
        });
    }

    public function markPaid(RiderCashOut $cashOut, int $reviewerId): bool
    {
        return DB::transaction(function () use ($cashOut, $reviewerId): bool {
            $lockedCashOut = RiderCashOut::query()->lockForUpdate()->findOrFail($cashOut->id);

            if ($lockedCashOut->status !== 'pending') {
                return false;
            }

            $lockedCashOut->update([
                'status' => 'paid',
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
                'paid_at' => now(),
            ]);

            return true;
        });
    }

    public function reject(RiderCashOut $cashOut, int $reviewerId, ?string $note = null): bool
    {
        return DB::transaction(function () use ($cashOut, $reviewerId, $note): bool {
            $lockedCashOut = RiderCashOut::query()->lockForUpdate()->findOrFail($cashOut->id);

            if ($lockedCashOut->status !== 'pending') {
                return false;
            }

            $wallet = Wallet::query()
                ->where('user_id', $lockedCashOut->user_id)
                ->lockForUpdate()
                ->firstOrFail();
            $wallet->credit($lockedCashOut->amount, "Refund for rejected cash-out #{$lockedCashOut->id}", $lockedCashOut);
            $lockedCashOut->update([
                'status' => 'rejected',
                'review_note' => $note,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
            ]);

            return true;
        });
    }
}
