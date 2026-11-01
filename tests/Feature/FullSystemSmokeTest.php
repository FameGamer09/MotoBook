<?php

use App\Models\Category;
use App\Models\CustomDelivery;
use App\Models\Delivery;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\RiderAssignmentService;

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

test('rider can accept an offered delivery and move it through every delivery stage', function () {
    $merchant = User::factory()->create(['role' => 'merchant', 'status' => 'active']);
    $store = Store::factory()->create([
        'user_id' => $merchant->id,
        'latitude' => 11.585,
        'longitude' => 122.751,
    ]);
    $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
    $riderUser = User::factory()->create(['role' => 'rider', 'status' => 'active']);
    $rider = $riderUser->riderProfile()->create([
        'vehicle_type' => 'motorcycle',
        'status' => 'online',
        'is_verified' => true,
        'current_latitude' => 11.585,
        'current_longitude' => 122.751,
    ]);
    $order = Order::create([
        'customer_id' => $customer->id,
        'store_id' => $store->id,
        'order_number' => 'RIDER-'.fake()->unique()->numerify('#####'),
        'status' => 'ready_for_pickup',
        'payment_method' => 'cod',
        'payment_status' => 'unpaid',
        'subtotal' => 100,
        'total_amount' => 100,
        'customer_lat' => 11.585,
        'customer_lng' => 122.751,
    ]);
    $delivery = app(RiderAssignmentService::class)->assignNearestRider($order);

    $actions = [
        ['rider.deliveries.accept', 'accepted'],
        ['rider.deliveries.startPickup', 'en_route_to_pickup'],
        ['rider.deliveries.arrivePickup', 'arrived_at_pickup'],
        ['rider.deliveries.confirmPickup', 'picked_up'],
    ];

    foreach ($actions as [$route, $status]) {
        $this->actingAs($riderUser)
            ->post(route($route, $delivery))
            ->assertRedirect();

        $this->assertDatabaseHas('deliveries', ['id' => $delivery->id, 'status' => $status]);
        $delivery->refresh();
    }

    $this->actingAs($riderUser)
        ->post(route('rider.deliveries.startCustomer', $delivery))
        ->assertRedirect(route('rider.orders.navigation', $order));
    $this->get(route('rider.orders.navigation', $order))->assertOk();
    $this->assertDatabaseHas('deliveries', ['id' => $delivery->id, 'status' => 'en_route_to_customer']);

    $this->post(route('rider.deliveries.arriveCustomer', $delivery))
        ->assertRedirect();
    $this->assertDatabaseHas('deliveries', ['id' => $delivery->id, 'status' => 'arrived_at_customer']);

    $this->actingAs($riderUser)
        ->post(route('rider.deliveries.complete', $delivery), ['tip' => 5])
        ->assertRedirect();

    $this->assertDatabaseHas('deliveries', ['id' => $delivery->id, 'status' => 'delivered', 'total_earning' => 35]);
    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'delivered']);
    $this->assertDatabaseHas('wallets', ['user_id' => $riderUser->id, 'balance' => 35]);
});

test('rider can skip a queued delivery and pass the turn to the next rider', function () {
    $merchant = User::factory()->create(['role' => 'merchant', 'status' => 'active']);
    $store = Store::factory()->create([
        'user_id' => $merchant->id,
        'latitude' => 11.585,
        'longitude' => 122.751,
    ]);
    $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
    $riderUser = User::factory()->create(['role' => 'rider', 'status' => 'active']);
    $rider = $riderUser->riderProfile()->create([
        'vehicle_type' => 'motorcycle',
        'status' => 'online',
        'is_verified' => true,
        'current_latitude' => 11.585,
        'current_longitude' => 122.751,
    ]);
    $nextRiderUser = User::factory()->create(['role' => 'rider', 'status' => 'active']);
    $nextRider = $nextRiderUser->riderProfile()->create([
        'vehicle_type' => 'motorcycle',
        'status' => 'online',
        'is_verified' => true,
        'current_latitude' => 11.585,
        'current_longitude' => 122.751,
    ]);
    $order = Order::create([
        'customer_id' => $customer->id,
        'store_id' => $store->id,
        'order_number' => 'DECLINE-'.fake()->unique()->numerify('#####'),
        'status' => 'ready_for_pickup',
        'payment_method' => 'cod',
        'payment_status' => 'unpaid',
        'subtotal' => 100,
        'total_amount' => 100,
        'customer_lat' => 11.585,
        'customer_lng' => 122.751,
    ]);
    $delivery = app(RiderAssignmentService::class)->assignNearestRider($order);

    $this->actingAs($riderUser)
        ->post(route('rider.deliveries.reject', $delivery))
        ->assertRedirect();

    $this->assertDatabaseHas('deliveries', ['id' => $delivery->id, 'status' => 'pending_assignment', 'rider_id' => null]);
    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'ready_for_pickup', 'rider_id' => null]);
    $this->assertDatabaseHas('riders', ['id' => $rider->id, 'status' => 'online']);
    $this->assertDatabaseHas('rider_delivery_offers', ['delivery_id' => $delivery->id, 'rider_id' => $rider->id, 'status' => 'declined']);
    $this->assertDatabaseHas('rider_delivery_offers', ['delivery_id' => $delivery->id, 'rider_id' => $nextRider->id, 'status' => 'offered']);
});

test('all eligible riders see offers but only the active turn can claim and a busy rider cannot claim another', function () {
    $merchant = User::factory()->create(['role' => 'merchant', 'status' => 'active']);
    $store = Store::factory()->create([
        'user_id' => $merchant->id,
        'latitude' => 11.585,
        'longitude' => 122.751,
    ]);
    $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
    $riders = collect([1, 2])->map(function (): array {
        $user = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        $rider = $user->riderProfile()->create([
            'vehicle_type' => 'motorcycle',
            'status' => 'online',
            'is_verified' => true,
            'current_latitude' => 11.585,
            'current_longitude' => 122.751,
        ]);

        return [$user, $rider];
    });
    [$firstUser, $firstRider] = $riders[0];
    [$secondUser, $secondRider] = $riders[1];
    $firstRider->update(['dispatch_turn_at' => now()->subHour()]);
    $secondRider->update(['dispatch_turn_at' => now()->subMinutes(30)]);

    $order = Order::create([
        'customer_id' => $customer->id,
        'store_id' => $store->id,
        'order_number' => 'QUEUE-'.fake()->unique()->numerify('#####'),
        'status' => 'ready_for_pickup',
        'payment_method' => 'cod',
        'payment_status' => 'unpaid',
        'subtotal' => 100,
        'total_amount' => 100,
        'customer_lat' => 11.585,
        'customer_lng' => 122.751,
    ]);
    $delivery = app(RiderAssignmentService::class)->assignNearestRider($order);

    $this->actingAs($firstUser)->getJson(route('rider.offers'))
        ->assertOk()
        ->assertJsonCount(1, 'offers')
        ->assertJsonPath('offers.0.can_accept', true);

    $this->actingAs($secondUser)->getJson(route('rider.offers'))
        ->assertOk()
        ->assertJsonCount(1, 'offers')
        ->assertJsonPath('offers.0.can_accept', false);

    $this->actingAs($secondUser)
        ->post(route('rider.deliveries.accept', $delivery))
        ->assertRedirect()
        ->assertSessionHasErrors();
    $this->assertDatabaseHas('deliveries', ['id' => $delivery->id, 'status' => 'pending_assignment', 'rider_id' => null]);

    $this->actingAs($firstUser)
        ->post(route('rider.deliveries.accept', $delivery))
        ->assertRedirect();
    $this->assertDatabaseHas('deliveries', ['id' => $delivery->id, 'status' => 'accepted', 'rider_id' => $firstRider->id]);
    $this->assertDatabaseHas('riders', ['id' => $firstRider->id, 'status' => 'busy']);

    $secondOrder = Order::create([
        'customer_id' => $customer->id,
        'store_id' => $store->id,
        'order_number' => 'QUEUE-'.fake()->unique()->numerify('#####'),
        'status' => 'ready_for_pickup',
        'payment_method' => 'cod',
        'payment_status' => 'unpaid',
        'subtotal' => 100,
        'total_amount' => 100,
        'customer_lat' => 11.585,
        'customer_lng' => 122.751,
    ]);
    $secondDelivery = app(RiderAssignmentService::class)->assignNearestRider($secondOrder);

    $this->assertDatabaseMissing('rider_delivery_offers', ['delivery_id' => $secondDelivery->id, 'rider_id' => $firstRider->id]);
    $this->assertDatabaseHas('rider_delivery_offers', ['delivery_id' => $secondDelivery->id, 'rider_id' => $secondRider->id]);
});

test('an unanswered rider turn expires and advances to the next rider', function () {
    $merchant = User::factory()->create(['role' => 'merchant', 'status' => 'active']);
    $store = Store::factory()->create([
        'user_id' => $merchant->id,
        'latitude' => 11.585,
        'longitude' => 122.751,
    ]);
    $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
    $riders = collect([1, 2])->map(function (): array {
        $user = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        $rider = $user->riderProfile()->create([
            'vehicle_type' => 'motorcycle',
            'status' => 'online',
            'is_verified' => true,
            'current_latitude' => 11.585,
            'current_longitude' => 122.751,
        ]);

        return [$user, $rider];
    });
    [, $firstRider] = $riders[0];
    [, $secondRider] = $riders[1];
    $order = Order::create([
        'customer_id' => $customer->id,
        'store_id' => $store->id,
        'order_number' => 'TIMEOUT-'.fake()->unique()->numerify('#####'),
        'status' => 'ready_for_pickup',
        'payment_method' => 'cod',
        'payment_status' => 'unpaid',
        'subtotal' => 100,
        'total_amount' => 100,
        'customer_lat' => 11.585,
        'customer_lng' => 122.751,
    ]);
    $delivery = app(RiderAssignmentService::class)->assignNearestRider($order);

    $this->travel(21)->seconds();
    $this->actingAs($riders[1][0])->getJson(route('rider.offers'))->assertOk();

    $this->assertDatabaseHas('rider_delivery_offers', ['delivery_id' => $delivery->id, 'rider_id' => $firstRider->id, 'status' => 'expired']);
    $this->assertDatabaseHas('rider_delivery_offers', ['delivery_id' => $delivery->id, 'rider_id' => $secondRider->id, 'status' => 'offered']);
});

test('verified rider can switch online and offline when no delivery is active', function () {
    $riderUser = User::factory()->create(['role' => 'rider', 'status' => 'active']);
    $rider = $riderUser->riderProfile()->create([
        'vehicle_type' => 'motorcycle',
        'status' => 'offline',
        'is_verified' => true,
    ]);

    $this->actingAs($riderUser)
        ->post(route('rider.status.toggle'))
        ->assertRedirect();
    $this->assertDatabaseHas('riders', ['id' => $rider->id, 'status' => 'online']);

    $this->post(route('rider.status.toggle'))->assertRedirect();
    $this->assertDatabaseHas('riders', ['id' => $rider->id, 'status' => 'offline']);
});

test('changing verified rider vehicle details requires verification again', function () {
    $riderUser = User::factory()->create(['role' => 'rider', 'status' => 'active']);
    $rider = $riderUser->riderProfile()->create([
        'vehicle_type' => 'motorcycle',
        'plate_number' => 'ABC-1234',
        'license_number' => 'LIC-12345',
        'status' => 'online',
        'is_verified' => true,
    ]);

    $this->actingAs($riderUser)
        ->patch(route('rider.profile.update'), [
            'name' => 'Updated Rider',
            'phone' => '09171234567',
            'vehicle_type' => 'motorcycle',
            'plate_number' => 'ABC-1234',
            'license_number' => 'LIC-12345',
        ])
        ->assertRedirect();
    $this->assertDatabaseHas('riders', ['id' => $rider->id, 'status' => 'online', 'is_verified' => true]);

    $this->patch(route('rider.profile.update'), [
        'name' => 'Updated Rider',
        'phone' => '09171234567',
        'vehicle_type' => 'motorcycle',
        'plate_number' => 'XYZ-9876',
        'license_number' => 'LIC-12345',
    ])
        ->assertRedirect()
        ->assertSessionHas('status');

    $this->assertDatabaseHas('users', ['id' => $riderUser->id, 'name' => 'Updated Rider']);
    $this->assertDatabaseHas('riders', [
        'id' => $rider->id,
        'plate_number' => 'XYZ-9876',
        'status' => 'offline',
        'is_verified' => false,
    ]);
});

test('rider profile rejects a phone number used by another account', function () {
    $riderUser = User::factory()->create(['role' => 'rider', 'status' => 'active', 'phone' => '09170000001']);
    $riderUser->riderProfile()->create(['vehicle_type' => 'motorcycle', 'status' => 'offline', 'is_verified' => false]);
    User::factory()->create(['role' => 'customer', 'status' => 'active', 'phone' => '09170000002']);

    $this->actingAs($riderUser)
        ->patch(route('rider.profile.update'), [
            'name' => $riderUser->name,
            'phone' => '09170000002',
            'vehicle_type' => 'motorcycle',
            'plate_number' => '',
            'license_number' => '',
        ])
        ->assertSessionHasErrors('phone');

    $this->assertDatabaseHas('users', ['id' => $riderUser->id, 'phone' => '09170000001']);
});

test('rider cannot go online while a custom delivery is active', function () {
    $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
    $riderUser = User::factory()->create(['role' => 'rider', 'status' => 'active']);
    $rider = $riderUser->riderProfile()->create([
        'vehicle_type' => 'motorcycle',
        'status' => 'offline',
        'is_verified' => true,
    ]);
    $job = CustomDelivery::create([
        'user_id' => $customer->id,
        'rider_id' => $rider->id,
        'pickup_address' => 'Pickup Road',
        'sender_name' => 'Sender',
        'sender_phone' => '09170000001',
        'dropoff_address' => 'Drop-off Road',
        'recipient_name' => 'Recipient',
        'recipient_phone' => '09170000002',
        'package_category' => 'documents',
        'description' => 'Test package',
        'total_amount' => 265,
        'status' => 'accepted',
    ]);

    $this->actingAs($riderUser)
        ->post(route('rider.status.toggle'))
        ->assertConflict();

    $this->assertDatabaseHas('riders', ['id' => $rider->id, 'status' => 'offline']);
    $this->assertDatabaseHas('custom_deliveries', ['id' => $job->id, 'status' => 'accepted']);
});

test('rider can decline a custom delivery offer and update its live location', function () {
    $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
    $riderUser = User::factory()->create(['role' => 'rider', 'status' => 'active']);
    $rider = $riderUser->riderProfile()->create([
        'vehicle_type' => 'motorcycle',
        'status' => 'busy',
        'is_verified' => true,
    ]);
    $job = CustomDelivery::create([
        'user_id' => $customer->id,
        'rider_id' => $rider->id,
        'pickup_address' => 'Pickup Road',
        'sender_name' => 'Sender',
        'sender_phone' => '09170000001',
        'dropoff_address' => 'Drop-off Road',
        'recipient_name' => 'Recipient',
        'recipient_phone' => '09170000002',
        'package_category' => 'documents',
        'description' => 'Test package',
        'total_amount' => 265,
        'status' => 'assigned',
    ]);

    $this->actingAs($riderUser)
        ->post(route('rider.custom-deliveries.reject', $job))
        ->assertRedirect();

    $this->assertDatabaseHas('custom_deliveries', ['id' => $job->id, 'status' => 'pending_assignment', 'rider_id' => null]);
    $this->assertDatabaseHas('riders', ['id' => $rider->id, 'status' => 'online']);

    $job->fresh()->update(['rider_id' => $rider->id, 'status' => 'accepted']);
    $this->postJson(route('rider.custom-deliveries.location', $job), [
        'latitude' => 11.586,
        'longitude' => 122.752,
    ])->assertOk()->assertJsonPath('latitude', '11.5860000');

    $this->assertDatabaseHas('riders', ['id' => $rider->id, 'current_latitude' => 11.586, 'current_longitude' => 122.752]);
});

test('rider dashboard only shows that rider deliveries and counts earnings from today', function () {
    $merchant = User::factory()->create(['role' => 'merchant', 'status' => 'active']);
    $store = Store::factory()->create(['user_id' => $merchant->id]);
    $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
    $riderUser = User::factory()->create(['role' => 'rider', 'status' => 'active']);
    $rider = $riderUser->riderProfile()->create(['vehicle_type' => 'motorcycle', 'status' => 'busy', 'is_verified' => true]);
    $otherRiderUser = User::factory()->create(['role' => 'rider', 'status' => 'active']);
    $otherRider = $otherRiderUser->riderProfile()->create(['vehicle_type' => 'motorcycle', 'status' => 'busy', 'is_verified' => true]);

    foreach ([[$rider, 'OWN-RIDER-ORDER'], [$otherRider, 'OTHER-RIDER-ORDER']] as [$assignedRider, $orderNumber]) {
        $order = Order::create([
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'order_number' => $orderNumber,
            'status' => 'assigned',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'subtotal' => 100,
            'total_amount' => 100,
            'rider_id' => $assignedRider->id,
        ]);
        Delivery::create(['order_id' => $order->id, 'rider_id' => $assignedRider->id, 'status' => 'assigned']);
    }

    $wallet = $riderUser->wallet()->create(['balance' => 100]);
    $wallet->credit(25, 'Today earning');
    $oldTransaction = $wallet->credit(75, 'Previous earning');
    $oldTransaction->forceFill(['created_at' => now()->subDay()])->save();

    $this->actingAs($riderUser)
        ->get(route('rider.dashboard'))
        ->assertOk()
        ->assertSee('OWN-RIDER-ORDER')
        ->assertDontSee('OTHER-RIDER-ORDER')
        ->assertViewHas('earnings', fn ($earnings): bool => (float) $earnings === 25.0);
});

test('rider live location is recorded only for the rider assigned delivery', function () {
    $merchant = User::factory()->create(['role' => 'merchant', 'status' => 'active']);
    $store = Store::factory()->create(['user_id' => $merchant->id]);
    $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
    $riderUser = User::factory()->create(['role' => 'rider', 'status' => 'active']);
    $rider = $riderUser->riderProfile()->create(['vehicle_type' => 'motorcycle', 'status' => 'busy', 'is_verified' => true]);
    $order = Order::create([
        'customer_id' => $customer->id,
        'store_id' => $store->id,
        'order_number' => 'LOCATION-'.fake()->unique()->numerify('#####'),
        'status' => 'assigned',
        'payment_method' => 'cod',
        'payment_status' => 'unpaid',
        'subtotal' => 100,
        'total_amount' => 100,
        'rider_id' => $rider->id,
    ]);
    $delivery = Delivery::create(['order_id' => $order->id, 'rider_id' => $rider->id, 'status' => 'assigned']);

    $this->actingAs($riderUser)
        ->postJson(route('rider.location.update'), [
            'delivery_id' => $delivery->id,
            'latitude' => 11.585,
            'longitude' => 122.751,
        ])
        ->assertOk()
        ->assertJsonPath('latitude', '11.5850000');

    $this->assertDatabaseHas('riders', ['id' => $rider->id, 'current_latitude' => 11.585, 'current_longitude' => 122.751]);
    $this->assertDatabaseHas('rider_location_logs', ['rider_id' => $rider->id, 'order_id' => $order->id]);
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
