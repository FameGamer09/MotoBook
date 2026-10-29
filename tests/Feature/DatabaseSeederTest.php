<?php

use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

test('database seeder can be run repeatedly without duplicating sample data', function () {
    $this->seed(DatabaseSeeder::class);

    $counts = [
        User::count(),
        Category::count(),
        Product::count(),
        InventoryMovement::count(),
    ];

    User::where('email', 'admin@example.com')->update([
        'password' => Hash::make('old-password'),
    ]);
    Product::where('sku', 'SKU-001-WM')->update(['quantity' => 3]);

    $this->seed(DatabaseSeeder::class);

    expect([
        User::count(),
        Category::count(),
        Product::count(),
        InventoryMovement::count(),
    ])->toBe($counts);

    expect(Hash::check(
        'password',
        User::where('email', 'admin@example.com')->value('password'),
    ))->toBeTrue();
    expect(Product::where('sku', 'SKU-001-WM')->value('quantity'))->toBe(3);
});
