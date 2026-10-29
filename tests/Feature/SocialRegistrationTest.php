<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

test('registration page offers Google and Facebook sign-up', function () {
    $this->get(route('register'))
        ->assertSuccessful()
        ->assertSee(route('register.social.google'))
        ->assertSee(route('register.social.facebook'));
});

test('new customer accounts can be registered with a social provider', function (string $provider) {
    config([
        "services.{$provider}.client_id" => 'test-client-id',
        "services.{$provider}.client_secret" => 'test-client-secret',
        "services.{$provider}.redirect" => "http://localhost/auth/{$provider}/callback",
    ]);

    $providerUser = SocialiteUser::fake([
        'id' => "{$provider}-user-id",
        'name' => 'Social Customer',
        'email' => "{$provider}@example.com",
        'verified_email' => $provider === 'google',
    ]);
    Socialite::fake($provider, $providerUser);

    $this->get(route("register.social.{$provider}"))
        ->assertRedirect("https://socialite.fake/{$provider}/authorize");

    $this->get("/auth/{$provider}/callback")
        ->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', "{$provider}@example.com")->firstOrFail();

    expect($user->role)->toBe('customer')
        ->and($user->status)->toBe('active')
        ->and($user->wallet)->not->toBeNull()
        ->and($user->email_verified_at !== null)->toBe($provider === 'google');

    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseHas('social_accounts', [
        'user_id' => $user->id,
        'provider' => $provider,
        'provider_user_id' => "{$provider}-user-id",
    ]);
})->with(['google', 'facebook']);

test('Google social registration rejects an unverified email', function () {
    config([
        'services.google.client_id' => 'test-client-id',
        'services.google.client_secret' => 'test-client-secret',
        'services.google.redirect' => 'http://localhost/auth/google/callback',
    ]);
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'unverified-google-user',
        'email' => 'unverified@example.com',
        'verified_email' => false,
    ]));

    $this->get(route('register.social.google'));

    $this->get('/auth/google/callback')
        ->assertRedirect(route('register'));

    $this->assertDatabaseMissing('users', ['email' => 'unverified@example.com']);
});
