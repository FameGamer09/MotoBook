<?php

use App\Models\Store;
use App\Models\User;
use Database\Seeders\TestAccountsSeeder;
use Illuminate\Support\Facades\Hash;

test('test account seeder creates usable accounts and related profiles idempotently', function () {
    $this->seed(TestAccountsSeeder::class);
    $this->seed(TestAccountsSeeder::class);

    expect(User::whereIn('email', [
        'customer@example.com',
        'rider@example.com',
        'merchant@example.com',
    ])->count())->toBe(3);

    foreach ([
        'customer@example.com' => 'customer',
        'rider@example.com' => 'rider',
        'merchant@example.com' => 'merchant',
    ] as $email => $role) {
        $user = User::where('email', $email)->firstOrFail();

        expect($user->role)->toBe($role)
            ->and($user->status)->toBe('active')
            ->and(Hash::check('password', $user->password))->toBeTrue()
            ->and($user->wallet)->not->toBeNull();
    }

    expect(User::where('email', 'rider@example.com')->firstOrFail()->riderProfile)->not->toBeNull()
        ->and(Store::where('slug', 'test-merchant-store')->count())->toBe(1);
});

test('test account seeder refuses to run outside local and testing environments', function () {
    app()->detectEnvironment(fn (): string => 'production');

    expect(fn () => app(TestAccountsSeeder::class)->run())
        ->toThrow(RuntimeException::class);

    expect(User::count())->toBe(0);
});
