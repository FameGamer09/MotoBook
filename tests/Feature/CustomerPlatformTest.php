<?php

use App\Models\Store;
use App\Models\User;

test('guests must authenticate before opening the customer storefront', function () {
    $this->get(route('customer.restaurants'))
        ->assertRedirect(route('login'));
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

test('non-customer accounts cannot open the customer storefront', function () {
    $merchant = User::factory()->create(['role' => 'merchant', 'status' => 'active']);

    $this->actingAs($merchant)
        ->get(route('customer.restaurants'))
        ->assertForbidden();
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
