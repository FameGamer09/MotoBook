<?php

use App\Models\Order;
use App\Models\Store;
use App\Models\User;

test('admin can cancel an active order', function () {
    $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
    $merchant = User::factory()->create(['role' => 'merchant', 'status' => 'active']);
    $store = Store::factory()->create(['user_id' => $merchant->id]);

    $order = Order::withoutGlobalScopes()->create([
        'order_number' => 'MB-TEST-CANCEL',
        'customer_id' => $customer->id,
        'store_id' => $store->id,
        'status' => 'ready_for_pickup',
        'payment_method' => 'cod',
        'payment_status' => 'unpaid',
        'subtotal' => 129,
        'delivery_fee' => 49,
        'service_fee' => 5,
        'discount_amount' => 0,
        'total_amount' => 183,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.orders.cancel', $order), [
            'reason' => 'Cancelled by admin for testing',
        ])
        ->assertRedirect();

    $order->refresh();
    expect($order->status)->toBe('cancelled')
        ->and($order->cancel_reason)->toBe('Cancelled by admin for testing')
        ->and($order->cancelled_at)->not->toBeNull();
});
