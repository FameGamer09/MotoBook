<?php

use App\Models\User;
use App\Services\SharedPanelAuthentication;

test('login screen is available to platform users', function () {
    $response = $this->get('/login');

    $response->assertOk()
        ->assertSee('Use your customer, rider, management, or admin account to sign in here.')
        ->assertDontSee('open the management sign-in');
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('management accounts can authenticate using the login screen', function () {
    $panelAuthentication = Mockery::mock(SharedPanelAuthentication::class);
    $panelAuthentication->shouldReceive('authenticate')
        ->once()
        ->with('staff@example.com', 'management-password')
        ->andReturn([
            'ok' => true,
            'panel' => 'management',
            'role' => 'platform_staff',
            'url' => '/A-management/orders.php?tab=kanban',
            'data' => ['id' => 1],
        ]);
    $panelAuthentication->shouldReceive('startPanelSession')->once();
    $this->app->instance(SharedPanelAuthentication::class, $panelAuthentication);

    $response = $this->post('/login', [
        'email' => 'staff@example.com',
        'password' => 'management-password',
    ]);

    $this->assertGuest();
    $response->assertRedirect('/A-management/orders.php?tab=kanban');
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});

test('authenticated pages are not cached by the browser', function () {
    $user = User::factory()->create([
        'role' => 'customer',
        'status' => 'active',
    ]);

    $response = $this->actingAs($user)->get(route('customer.restaurants'));

    $response->assertSuccessful();
    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('Cache-Control'))->toContain('no-cache');
});

test('login page is not cached by the browser', function () {
    $response = $this->get(route('login'));

    $response->assertSuccessful();
    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('Cache-Control'))->toContain('no-cache');
});

test('authenticated users are redirected away from the login page', function () {
    $user = User::factory()->create(['status' => 'active']);

    $this->actingAs($user)
        ->get(route('login'))
        ->assertRedirect(route('dashboard'));
});
