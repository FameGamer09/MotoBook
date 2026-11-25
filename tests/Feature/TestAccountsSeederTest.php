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
        'jollibee@example.com',
        'manginasal@example.com',
        'bigbrew@example.com',
        'pizzahut@example.com',
    ])->count())->toBe(7);

    foreach ([
        'customer@example.com' => 'customer',
        'rider@example.com' => 'rider',
        'merchant@example.com' => 'merchant',
        'jollibee@example.com' => 'merchant',
        'manginasal@example.com' => 'merchant',
        'bigbrew@example.com' => 'merchant',
        'pizzahut@example.com' => 'merchant',
    ] as $email => $role) {
        $user = User::where('email', $email)->firstOrFail();

        expect($user->role)->toBe($role)
            ->and($user->status)->toBe('active')
            ->and(Hash::check('password', $user->password))->toBeTrue()
            ->and($user->wallet)->not->toBeNull();
    }

    expect(User::where('email', 'rider@example.com')->firstOrFail()->riderProfile)->not->toBeNull()
        ->and(Store::where('slug', 'test-merchant-store')->count())->toBe(1)
        ->and(Store::whereIn('slug', [
            'jollibee-roxas-city',
            'mang-inasal-roxas-city',
            'bigbrew-roxas-city',
            'pizza-hut-roxas-city',
        ])->count())->toBe(4);

    foreach ([
        'jollibee@example.com' => 'Jollibee',
        'manginasal@example.com' => 'Mang Inasal',
        'bigbrew@example.com' => 'BigBrew',
        'pizzahut@example.com' => 'Pizza Hut',
    ] as $email => $storeName) {
        $merchant = User::where('email', $email)->firstOrFail();

        expect($merchant->store?->name)->toBe($storeName);
    }

    foreach ([
        'jollibee-roxas-city' => ['Chickenjoy with Rice', 6],
        'mang-inasal-roxas-city' => ['Chicken Inasal Paa', 6],
        'bigbrew-roxas-city' => ['Okinawa Milk Tea', 6],
        'pizza-hut-roxas-city' => ['Pepperoni Pizza', 6],
    ] as $slug => [$featuredItem, $itemCount]) {
        $store = Store::where('slug', $slug)->firstOrFail();

        expect($store->menuCategories()->count())->toBe(3)
            ->and($store->menuItems()->count())->toBe($itemCount)
            ->and($store->menuItems()->where('name', $featuredItem)->whereNotNull('menu_category_id')->exists())->toBeTrue();
    }
});

test('test account seeder refuses to run outside local and testing environments', function () {
    app()->detectEnvironment(fn (): string => 'production');

    expect(fn () => app(TestAccountsSeeder::class)->run())
        ->toThrow(RuntimeException::class);

    expect(User::count())->toBe(0);
});
