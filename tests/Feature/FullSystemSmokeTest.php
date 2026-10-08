<?php

use App\Models\Category;
use App\Models\Delivery;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\CustomDelivery;
use App\Models\Product;
use App\Models\Rider;
use App\Models\Store;
use App\Models\User;

test('all role dashboards and primary screens render', function () {
    $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    $cashier = User::factory()->create(['role' => 'cashier', 'status' => 'active']);
    $merchant = User::factory()->create(['role' => 'merchant', 'status' => 'active']);
    $store = Store::factory()->create(['user_id' => $merchant->id]);
    $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
    $customer->wallet()->create(['balance' => 1000]);
    $customer->addresses()->create([
        'label' => 'Home', 'address_line' => '1 Test St', 'latitude' => 11.585, 'longitude' => 122.751,
        'is_default' => true,
    ]);
    $rider = User::factory()->create(['role' => 'rider', 'status' => 'active']);
    $rider->riderProfile()->create(['vehicle_type' => 'motorcycle', 'status' => 'offline', 'is_verified' => true]);

    foreach ([
        [$admin, 'admin.dashboard'],
        [$admin, 'admin.merchants.index'],
        [$admin, 'admin.riders.index'],
        [$admin, 'admin.customers.index'],
        [$admin, 'admin.promotions.index'],
        [$cashier, 'dashboard'],
        [$cashier, 'products.index'],
        [$cashier, 'categories.index'],
        [$cashier, 'transactions.index'],
        [$cashier, 'pos.index'],
        [$cashier, 'profile.edit'],
        [$merchant, 'merchant.dashboard'],
        [$merchant, 'merchant.orders.index'],
        [$merchant, 'merchant.menu.index'],
        [$merchant, 'merchant.store.edit'],
        [$merchant, 'merchant.sales.index'],
        [$merchant, 'merchant.settings.edit'],
        [$customer, 'customer.restaurants'],
        [$customer, 'customer.profile'],
        [$customer, 'customer.profile.edit'],
        [$customer, 'customer.addresses'],
        [$customer, 'customer.cart'],
        [$customer, 'customer.orders.index'],
        [$rider, 'rider.dashboard'],
        [$rider, 'rider.profile'],
    ] as [$user, $route]) {
        $this->actingAs($user)->get(route($route))->assertOk();
    }
});

test('POS sale and cancellation restore inventory', function () {
    $cashier = User::factory()->create(['role' => 'cashier', 'status' => 'active']);
    $category = Category::create(['name' => 'Smoke Category', 'description' => 'Test']);
    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'Smoke Product',
        'sku' => 'SMOKE-001',
        'price' => 10,
        'quantity' => 5,
        'low_stock_threshold' => 1,
        'is_active' => true,
    ]);

    $response = $this->actingAs($cashier)->postJson(route('pos.processSale'), [
        'items' => [['product_id' => $product->id, 'quantity' => 2]],
        'payment_method' => 'cash',
    ]);

    $response->assertOk()->assertJsonPath('success', true);
    $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity' => 3]);

    $transactionId = $response->json('transaction.id');
    $this->actingAs($cashier)->delete(route('transactions.destroy', $transactionId))->assertRedirect(route('transactions.index'));
    $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity' => 5]);
    $this->assertDatabaseHas('transactions', ['id' => $transactionId, 'status' => 'cancelled']);
});

test('customer can place an order and merchant can advance it to pickup', function () {
    $merchant = User::factory()->create(['role' => 'merchant', 'status' => 'active']);
    $store = Store::factory()->create(['user_id' => $merchant->id, 'is_verified' => true, 'is_open' => true]);
    $item = MenuItem::factory()->create(['store_id' => $store->id, 'price' => 125]);
    $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
    $address = $customer->addresses()->create([
        'label' => 'Home', 'address_line' => '2 Test St', 'latitude' => 11.585, 'longitude' => 122.751,
        'is_default' => true,
    ]);
    $customer->wallet()->create(['balance' => 1000]);

    $this->actingAs($customer)
        ->post(route('customer.cart.add', $item), ['quantity' => 2])
        ->assertRedirect();

    $this->post(route('customer.checkout.place'), [
        'address_id' => $address->id,
        'payment_method' => 'cod',
    ])->assertRedirect();

    $order = Order::query()->latest('id')->firstOrFail();
    $this->assertDatabaseHas('orders', ['id' => $order->id, 'customer_id' => $customer->id, 'status' => 'pending']);
    $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'quantity' => 2, 'subtotal' => 250]);

    $this->actingAs($merchant)
        ->post(route('merchant.orders.accept', $order))->assertRedirect();
    $this->post(route('merchant.orders.preparing', $order))->assertRedirect();
    $this->post(route('merchant.orders.ready', $order))->assertRedirect();

    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'ready_for_pickup']);
    $this->assertDatabaseHas('deliveries', ['order_id' => $order->id, 'status' => 'pending_assignment']);
});

test('merchant settings save and riders can complete only their assigned deliveries', function () {
    $merchant = User::factory()->create(['role' => 'merchant', 'status' => 'active']);
    $store = Store::factory()->create(['user_id' => $merchant->id]);
    $this->actingAs($merchant)->patch(route('merchant.settings.update'), [
        'order_notifications' => '1',
        'weekly_sales_summary' => '1',
    ])->assertRedirect();
    expect($store->fresh()->settings)->toMatchArray([
        'order_notifications' => true,
        'low_stock_alerts' => false,
        'weekly_sales_summary' => true,
    ]);

    $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
    $otherRiderUser = User::factory()->create(['role' => 'rider', 'status' => 'active']);
    $otherRider = $otherRiderUser->riderProfile()->create(['vehicle_type' => 'motorcycle', 'status' => 'offline']);
    $riderUser = User::factory()->create(['role' => 'rider', 'status' => 'active']);
    $rider = $riderUser->riderProfile()->create(['vehicle_type' => 'motorcycle', 'status' => 'busy', 'is_verified' => true]);
    $order = Order::create([
        'customer_id' => $customer->id,
        'store_id' => $store->id,
        'order_number' => 'SMOKE-'.fake()->unique()->numerify('#####'),
        'status' => 'out_for_delivery',
        'payment_method' => 'cod',
        'payment_status' => 'unpaid',
        'subtotal' => 100,
        'total_amount' => 100,
    ]);
    $delivery = Delivery::create([
        'order_id' => $order->id,
        'rider_id' => $rider->id,
        'status' => 'arrived_at_customer',
        'base_fare' => 35,
    ]);

    $this->actingAs($otherRiderUser)
        ->post(route('rider.deliveries.complete', $delivery))
        ->assertNotFound();

    $this->actingAs($riderUser)
        ->post(route('rider.deliveries.complete', $delivery), ['tip' => 5])
        ->assertRedirect();
    $this->assertDatabaseHas('deliveries', ['id' => $delivery->id, 'status' => 'delivered', 'total_earning' => 40]);
    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'delivered']);
    $this->assertDatabaseHas('wallets', ['user_id' => $riderUser->id, 'balance' => 40]);
});

test('custom delivery request is assigned, tracked, completed, and reported in notifications', function () {
    $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
    $riderUser = User::factory()->create(['role' => 'rider', 'status' => 'active']);
    $rider = $riderUser->riderProfile()->create([
        'vehicle_type' => 'motorcycle', 'status' => 'online', 'is_verified' => true,
        'current_latitude' => 11.585, 'current_longitude' => 122.751,
    ]);

    $this->actingAs($customer)->post(route('customer.custom-delivery.store'), [
        'pickup_address' => 'Pickup Road',
        'pickup_landmark' => 'Blue gate',
        'sender_name' => 'Sender',
        'sender_phone' => '09170000001',
        'dropoff_address' => 'Drop-off Street',
        'dropoff_landmark' => 'Market entrance',
        'recipient_name' => 'Recipient',
        'recipient_phone' => '09170000002',
        'package_category' => 'documents',
        'description' => 'Signed contract papers',
        'payment_method' => 'cod',
    ])->assertRedirect();

    $job = CustomDelivery::query()->firstOrFail();
    expect($job->rider_id)->toBe($rider->id)->and($job->status)->toBe('assigned');
    $this->assertDatabaseHas('customer_notifications', ['user_id' => $customer->id, 'type' => 'delivery']);

    $this->actingAs($riderUser)->post(route('rider.custom-deliveries.accept', $job))->assertRedirect();
    $this->post(route('rider.custom-deliveries.pickup', $job))->assertRedirect();
    $this->post(route('rider.custom-deliveries.complete', $job))->assertRedirect();
    $this->assertDatabaseHas('custom_deliveries', ['id' => $job->id, 'status' => 'delivered']);
    $this->assertDatabaseHas('wallets', ['user_id' => $riderUser->id, 'balance' => 250]);

    $this->actingAs($customer)->get(route('customer.custom-deliveries.show', $job))->assertOk();
    $this->get(route('customer.notifications'))->assertOk();
    $this->delete(route('customer.notifications.clear'))->assertRedirect();
    $this->assertDatabaseMissing('customer_notifications', ['user_id' => $customer->id]);
});

test('customer payment preferences and dark mode persist', function () {
    $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);

    $this->actingAs($customer)->patch(route('customer.payments.update'), [
        'preferred_payment' => 'gcash',
        'gcash_name' => 'Demo Customer',
        'gcash_number' => '09171234567',
    ])->assertRedirect();
    $this->patch(route('customer.settings.update'), [
        'dark_mode' => '1',
        'order_notifications' => '1',
    ])->assertRedirect();

    expect($customer->fresh()->customer_settings)->toMatchArray([
        'preferred_payment' => 'gcash',
        'gcash_number' => '09171234567',
        'dark_mode' => true,
        'order_notifications' => true,
    ]);
});
