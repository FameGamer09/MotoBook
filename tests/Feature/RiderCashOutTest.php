<?php

use App\Models\Rider;
use App\Models\RiderCashOutRequest;
use App\Models\User;
use App\Models\Wallet;

test('rider can request a cash-out from earnings and admin rejection refunds it', function () {
    $rider = User::factory()->create(['role' => 'rider', 'status' => 'active']);
    Rider::factory()->create(['user_id' => $rider->id]);
    $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    $wallet = Wallet::query()->create(['user_id' => $rider->id, 'balance' => 500]);

    $this->actingAs($rider)
        ->post(route('rider.wallet.cash-outs.store'), [
            'amount' => 200,
            'account_name' => 'Rider Name',
            'account_number' => '09123456789',
        ])
        ->assertRedirect(route('rider.wallet'));

    $cashOut = RiderCashOutRequest::query()->sole();
    expect((float) $wallet->fresh()->balance)->toBe(300.0)
        ->and($cashOut->status)->toBe('pending');

    $this->actingAs($admin)
        ->patch(route('admin.cash-outs.reject', $cashOut), ['review_note' => 'Unable to verify account'])
        ->assertRedirect();

    expect((float) $wallet->fresh()->balance)->toBe(500.0)
        ->and($cashOut->fresh()->status)->toBe('rejected')
        ->and($cashOut->fresh()->review_note)->toBe('Unable to verify account');
});

test('admin can mark a cash-out as paid once', function () {
    $rider = User::factory()->create(['role' => 'rider', 'status' => 'active']);
    $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    $cashOut = RiderCashOutRequest::query()->create([
        'user_id' => $rider->id,
        'amount' => 120,
        'payout_method' => 'gcash',
        'account_name' => 'Rider Name',
        'account_number' => '09123456789',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.cash-outs.paid', $cashOut))
        ->assertRedirect();

    expect($cashOut->fresh()->status)->toBe('paid')
        ->and($cashOut->fresh()->paid_at)->not->toBeNull();
});

test('rider cannot cash out more than the available earnings balance', function () {
    $rider = User::factory()->create(['role' => 'rider', 'status' => 'active']);
    Rider::factory()->create(['user_id' => $rider->id]);
    $wallet = Wallet::query()->create(['user_id' => $rider->id, 'balance' => 50]);

    $this->actingAs($rider)
        ->post(route('rider.wallet.cash-outs.store'), [
            'amount' => 100,
            'account_name' => 'Rider Name',
            'account_number' => '09123456789',
        ])
        ->assertSessionHasErrors('amount');

    expect((float) $wallet->fresh()->balance)->toBe(50.0)
        ->and(RiderCashOutRequest::query()->count())->toBe(0);
});
