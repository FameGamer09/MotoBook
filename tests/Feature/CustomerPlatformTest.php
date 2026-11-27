<?php

use App\Models\Order;
use App\Models\Store;
use App\Models\User;

test('guests must authenticate before opening the customer storefront', function () {
    $this->get(route('customer.restaurants'))
        ->assertRedirect(route('login'));
});

test('customers can choose delivery coordinates from a map on saved addresses', function () {
    $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);

    $this->actingAs($customer)
        ->get(route('customer.addresses'))
        ->assertOk()
        ->assertSee('data-map-picker', false)
        ->assertSee('data-location-map', false)
        ->assertSee('Tap the map or drag the pin', false)
        ->assertSee('openstreetmap.org/copyright', false);
});

test('checkout confirmation renders the restaurant from the order relationship', function () {
    $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
    $merchant = User::factory()->create(['role' => 'merchant', 'status' => 'active']);
    $store = Store::factory()->create(['user_id' => $merchant->id]);
    $order = Order::create([
        'customer_id' => $customer->id,
        'store_id' => $store->id,
        'status' => 'pending',
        'payment_method' => 'cod',
        'payment_status' => 'unpaid',
        'subtotal' => 129,
        'delivery_fee' => 49,
        'service_fee' => 5,
        'total_amount' => 183,
        'delivery_address_snapshot' => ['address_line' => 'Test address'],
    ]);

    $this->actingAs($customer)
        ->get(route('customer.checkout.confirmation', $order))
        ->assertOk()
        ->assertSee($store->name)
        ->assertSee('sent to nearby riders');
});

test('customers only see verified stores', function () {
    $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
    $merchant = User::factory()->create(['role' => 'merchant', 'status' => 'active']);
    $visibleStore = Store::factory()->create(['user_id' => $merchant->id]);
    $hiddenStore = Store::factory()->create([
        'user_id' => User::factory()->create(['role' => 'merchant'])->id,
        'is_verified' => false,
    ]);

    $this->actingAs($customer)
        ->get(route('customer.restaurants'))
        ->assertOk()
        ->assertSee($visibleStore->name)
        ->assertDontSee($hiddenStore->name);
});

test('customer home lets customers customize category tabs and uses service icons', function () {
    $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
    Store::factory()->create([
        'user_id' => User::factory()->create(['role' => 'merchant', 'status' => 'active'])->id,
        'category' => 'Burgers',
    ]);

    $this->actingAs($customer)
        ->get(route('customer.restaurants'))
        ->assertOk()
        ->assertSee('aria-label="Customize categories"', false)
        ->assertSee('id="category-preferences"', false)
        ->assertSee('data-category-preference', false)
        ->assertSee('class="quick-action-icon"', false);
});

test('see all jumps to a complete shop list without including Moto AI', function () {
    $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
    $merchant = User::factory()->create(['role' => 'merchant', 'status' => 'active']);
    $firstShop = Store::factory()->create(['user_id' => $merchant->id, 'name' => 'First Shop', 'rating' => 4.9]);
    $secondShop = Store::factory()->create(['user_id' => User::factory()->create(['role' => 'merchant'])->id, 'name' => 'Second Shop', 'rating' => 4.8]);
    $thirdShop = Store::factory()->create(['user_id' => User::factory()->create(['role' => 'merchant'])->id, 'name' => 'Third Shop', 'rating' => 4.7]);

    $response = $this->actingAs($customer)
        ->get(route('customer.restaurants'))
        ->assertOk()
        ->assertSee('href="#all-shops"', false)
        ->assertSee('id="all-shops"', false)
        ->assertSee('Popular Near You')
        ->assertSee('All Shops')
        ->assertSee($firstShop->name)
        ->assertSee($secondShop->name)
        ->assertSee($thirdShop->name)
        ->assertDontSee('Moto AI');

    $response->assertViewHas('popularStores', fn ($popularStores) => $popularStores->pluck('id')->all() === [
        $firstShop->id,
        $secondShop->id,
    ]);
});

test('custom delivery form presents package choices and a fixed order action', function () {
    $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);

    $this->actingAs($customer)
        ->get(route('customer.custom-delivery.create'))
        ->assertOk()
        ->assertSee('Pickup details')
        ->assertSee('Drop-off details')
        ->assertSee('name="package_category" value="documents"', false)
        ->assertSee('name="package_category" value="small_parcel"', false)
        ->assertSee('name="package_category" value="other"', false)
        ->assertSee('Verify Order Details')
        ->assertDontSee('aria-label="Customer navigation"', false);
});

test('customer navigation only shows the inbox indicator for unread messages', function (string $notificationState, bool $expectsIndicator) {
    $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);

    if ($notificationState !== 'empty') {
        $customer->customerNotifications()->create([
            'type' => 'order',
            'title' => 'Order update',
            'message' => 'Your order is on its way.',
            'read_at' => $notificationState === 'read' ? now() : null,
        ]);
    }

    $response = $this->actingAs($customer)
        ->get(route('customer.restaurants'))
        ->assertOk();

    if ($expectsIndicator) {
        $response->assertSee('class="cx-bottom-dot"', false);
    } else {
        $response->assertDontSee('class="cx-bottom-dot"', false);
    }
})->with([
    'empty inbox' => ['empty', false],
    'unread message' => ['unread', true],
    'read message' => ['read', false],
]);

test('non-customer accounts cannot open the customer storefront', function () {
    $merchant = User::factory()->create(['role' => 'merchant', 'status' => 'active']);

    $this->actingAs($merchant)
        ->get(route('customer.restaurants'))
        ->assertForbidden();
});

test('riders without a profile can log in and reach onboarding instead of a not found page', function () {
    $rider = User::factory()->create(['role' => 'rider', 'status' => 'active']);

    $this->post(route('login'), [
        'email' => $rider->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->get(route('dashboard'))
        ->assertRedirect(route('rider.onboarding'));

    $this->get(route('rider.onboarding'))
        ->assertOk()
        ->assertViewHas('rider', fn ($profile) => $profile->user_id === $rider->id);

    $this->assertDatabaseHas('riders', [
        'user_id' => $rider->id,
        'vehicle_type' => 'motorcycle',
        'status' => 'offline',
    ]);
});

test('public registration creates an active customer account and wallet', function () {
    $response = $this->post(route('register'), [
        'name' => 'Customer Example',
        'email' => 'customer@example.com',
        'phone' => '09171234567',
        'role' => 'customer',
        'password' => 'StrongPassword!123',
        'password_confirmation' => 'StrongPassword!123',
    ]);

    $user = User::where('email', 'customer@example.com')->firstOrFail();

    $response->assertRedirect(route('customer.restaurants'));
    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'role' => 'customer',
        'status' => 'active',
    ]);
    $this->assertDatabaseHas('wallets', ['user_id' => $user->id, 'balance' => 0]);
});

test('merchant and rider registration remains pending admin activation', function (string $role, string $onboardingRoute) {
    $email = "{$role}@example.com";
    $response = $this->post(route('register'), [
        'name' => ucfirst($role).' Applicant',
        'email' => $email,
        'phone' => $role === 'rider' ? '09171234568' : '09171234569',
        'role' => $role,
        'password' => 'StrongPassword!123',
        'password_confirmation' => 'StrongPassword!123',
    ]);

    $user = User::where('email', $email)->firstOrFail();

    $response->assertRedirect(route($onboardingRoute));
    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'role' => $role,
        'status' => 'inactive',
    ]);

    if ($role === 'rider') {
        $this->assertDatabaseHas('riders', ['user_id' => $user->id, 'status' => 'offline']);
    }
})->with([
    'merchant' => ['merchant', 'merchant.onboarding'],
    'rider' => ['rider', 'rider.onboarding'],
]);

test('public registration cannot create an administrator account', function () {
    $this->post(route('register'), [
        'name' => 'Admin Applicant',
        'email' => 'admin-applicant@example.com',
        'phone' => '09171234570',
        'role' => 'admin',
        'password' => 'StrongPassword!123',
        'password_confirmation' => 'StrongPassword!123',
    ])->assertSessionHasErrors('role');

    $this->assertDatabaseMissing('users', ['email' => 'admin-applicant@example.com']);
});
