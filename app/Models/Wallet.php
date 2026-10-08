<?php

namespace App\Models;

use App\Exceptions\InsufficientWalletFundsException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Wallet extends Model
{
    protected $fillable = ['user_id', 'balance', 'currency'];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * Credit the wallet and log the transaction atomically.
     */
    public function credit(float $amount, ?string $description = null, $reference = null): WalletTransaction
    {
        return DB::transaction(function () use ($amount, $description, $reference) {
            $this->increment('balance', $amount);
            $this->refresh();

            return $this->transactions()->create([
                'type' => 'credit',
                'amount' => $amount,
                'balance_after' => $this->balance,
                'description' => $description,
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference?->id,
            ]);
        });
    }

    /**
     * Debit the wallet and log the transaction atomically.
     * Throws if balance would go negative.
     */
    public function debit(float $amount, ?string $description = null, $reference = null): WalletTransaction
    {
        return DB::transaction(function () use ($amount, $description, $reference) {
            $wallet = static::query()->lockForUpdate()->findOrFail($this->id);

            if ($wallet->balance < $amount) {
                throw new InsufficientWalletFundsException('Insufficient wallet balance.');
            }

            $wallet->decrement('balance', $amount);
            $wallet->refresh();

            return $wallet->transactions()->create([
                'type' => 'debit',
                'amount' => $amount,
                'balance_after' => $wallet->balance,
                'description' => $description,
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference?->id,
            ]);
        });
    }
}
