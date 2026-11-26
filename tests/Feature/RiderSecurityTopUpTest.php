<?php

use App\Models\Rider;
use App\Models\RiderSecurityTopUp;
use App\Models\User;

test('rider sees a separate security deposit balance and top-up form', function () {
    $rider = User::factory()->create(['role' => 'rider', 'status' => 'active']);
    Rider::factory()->create(['user_id' => $rider->id]);
    $rider->riderSecurityDeposit()->create(['balance' => 75]);

    $this->actingAs($rider)
        ->get(route('rider.wallet'))
        ->assertSuccessful()
        ->assertSee('Security Deposit')
        ->assertSee('Send Top-up Request')
        ->assertSee('75.00');
});

test('rider top-up requests stay pending until an admin verifies payment', function () {
    $rider = User::factory()->create(['role' => 'rider', 'status' => 'active']);
    Rider::factory()->create(['user_id' => $rider->id]);

    $this->actingAs($rider)
        ->post(route('rider.wallet.security-top-ups.store'), [
            'amount' => '250.00',
            'payment_method' => 'gcash',
            'payment_reference' => 'GC-123456',
        ])
        ->assertRedirect(route('rider.wallet'));

    $topUp = RiderSecurityTopUp::query()->firstOrFail();

    expect($topUp->status)->toBe('pending')
        ->and($rider->fresh()->riderSecurityDeposit->balance)->toBe('0.00');
});

test('admin approval credits the security deposit once without changing earnings wallet', function () {
    $rider = User::factory()->create(['role' => 'rider', 'status' => 'active']);
    Rider::factory()->create(['user_id' => $rider->id]);
    $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    $deposit = $rider->riderSecurityDeposit()->create(['balance' => 0]);
    $topUp = $rider->riderSecurityTopUps()->create([
        'amount' => 250,
        'payment_method' => 'cash',
        'status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.security-top-ups.index'))
        ->assertSuccessful()
        ->assertSee($rider->name)
        ->assertSee('Approve');

    $this->actingAs($admin)
        ->patch(route('admin.security-top-ups.approve', $topUp))
        ->assertRedirect();

    $this->actingAs($admin)
        ->patch(route('admin.security-top-ups.approve', $topUp))
        ->assertRedirect();

    expect($deposit->fresh()->balance)->toBe('250.00')
        ->and($topUp->fresh()->status)->toBe('approved')
        ->and($topUp->fresh()->balance_after)->toBe('250.00')
        ->and($rider->wallet()->exists())->toBeFalse();
});

test('rider must provide a GCash reference and customers cannot submit rider top-ups', function () {
    $rider = User::factory()->create(['role' => 'rider', 'status' => 'active']);
    Rider::factory()->create(['user_id' => $rider->id]);

    $this->actingAs($rider)
        ->from(route('rider.wallet'))
        ->post(route('rider.wallet.security-top-ups.store'), [
            'amount' => 250,
            'payment_method' => 'gcash',
        ])
        ->assertRedirect(route('rider.wallet'))
        ->assertSessionHasErrors('payment_reference');

    $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);

    $this->actingAs($customer)
        ->post(route('rider.wallet.security-top-ups.store'), [
            'amount' => 250,
            'payment_method' => 'cash',
        ])
        ->assertForbidden();
});
