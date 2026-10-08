<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    protected $fillable = [
        'wallet_id', 'type', 'amount', 'balance_after', 'reference_type', 'reference_id', 'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    // polymorphic: reference_type/reference_id -> the Order, Delivery, or Payout that caused this
    public function reference()
    {
        return $this->morphTo();
    }
}
