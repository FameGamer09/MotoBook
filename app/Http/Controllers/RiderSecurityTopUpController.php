<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRiderSecurityTopUpRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class RiderSecurityTopUpController extends Controller
{
    public function store(StoreRiderSecurityTopUpRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        DB::transaction(function () use ($user, $data): void {
            $user->riderSecurityDeposit()->firstOrCreate([], ['balance' => 0]);
            $user->riderSecurityTopUps()->create([
                'amount' => $data['amount'],
                'payment_method' => $data['payment_method'],
                'payment_reference' => $data['payment_reference'] ?? null,
            ]);
        });

        return to_route('rider.wallet')->with('status', 'Your security top-up request was sent for admin review.');
    }
}
