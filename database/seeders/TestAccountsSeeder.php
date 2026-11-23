<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TestAccountsSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Test accounts may only be seeded in local or testing environments.');
        }

        DB::transaction(function (): void {
            User::query()
                ->whereIn('email', [
                    'cashier@example.com',
                    'jollibee@example.com',
                    'manginasal@example.com',
                    'bigbrew@example.com',
                    'pizzahut@example.com',
                ])
                ->delete();

            User::updateOrCreate(
                ['email' => 'admin@example.com'],
                [
                    'name' => 'Admin',
                    'password' => 'password',
                    'role' => 'admin',
                    'status' => 'active',
                ],
            );

            $customer = User::updateOrCreate(
                ['email' => 'customer@example.com'],
                [
                    'name' => 'Test Customer',
                    'phone' => '+15555550101',
                    'password' => 'password',
                    'role' => 'customer',
                    'status' => 'active',
                ],
            );
            $customer->wallet()->firstOrCreate([], ['balance' => 0]);

            $rider = User::updateOrCreate(
                ['email' => 'rider@example.com'],
                [
                    'name' => 'Test Rider',
                    'phone' => '+15555550102',
                    'password' => 'password',
                    'role' => 'rider',
                    'status' => 'active',
                ],
            );
            $rider->wallet()->firstOrCreate([], ['balance' => 0]);
            $rider->riderProfile()->updateOrCreate([], [
                'vehicle_type' => 'motorcycle',
                'status' => 'offline',
                'is_verified' => true,
            ]);

            $merchant = User::updateOrCreate(
                ['email' => 'merchant@example.com'],
                [
                    'name' => 'Jollibee Merchant',
                    'phone' => '+15555550103',
                    'password' => 'password',
                    'role' => 'merchant',
                    'status' => 'active',
                ],
            );
            $merchant->wallet()->firstOrCreate([], ['balance' => 0]);

            Store::query()
                ->where('user_id', $merchant->id)
                ->where('slug', 'test-merchant-store')
                ->delete();

            $store = Store::updateOrCreate(
                ['slug' => 'jollibee-roxas-city'],
                [
                    'user_id' => $merchant->id,
                    'name' => 'Jollibee',
                    'slug' => 'jollibee-roxas-city',
                    'description' => 'Jollibee fast-food restaurant.',
                    'category' => 'Fast Food',
                    'address' => 'Roxas City, Capiz',
                    'phone' => $merchant->phone,
                    'is_open' => true,
                    'is_verified' => true,
                ],
            );

            $menu = [
                'Chickenjoy and Rice Meals' => [
                    ['name' => '1 pc Chickenjoy with Rice', 'description' => 'One piece of crispy fried chicken served with rice and gravy.', 'price' => 129],
                    ['name' => '2 pc Chickenjoy with Rice', 'description' => 'Two pieces of crispy fried chicken served with rice and gravy.', 'price' => 199],
                    ['name' => 'Chickenjoy Bucket', 'description' => 'A shareable bucket of crispy fried chicken.', 'price' => 499],
                    ['name' => 'Burger Steak with Rice', 'description' => 'Savory burger patties with mushroom gravy and rice.', 'price' => 99],
                ],
                'Spaghetti and Pasta' => [
                    ['name' => 'Jolly Spaghetti', 'description' => 'Filipino-style spaghetti with a sweet sauce.', 'price' => 79],
                    ['name' => 'Jolly Spaghetti Family Pan', 'description' => 'A family-sized pan of Filipino-style spaghetti.', 'price' => 249],
                    ['name' => 'Palabok Fiesta', 'description' => 'Rice noodles with savory palabok sauce and toppings.', 'price' => 129],
                ],
                'Burgers and Sandwiches' => [
                    ['name' => 'Yumburger', 'description' => 'A classic burger with a savory dressing.', 'price' => 45],
                    ['name' => 'Cheesy Yumburger', 'description' => 'A Yumburger topped with cheese.', 'price' => 59],
                    ['name' => 'Jolly Hotdog', 'description' => 'A hotdog topped with cheese and dressing.', 'price' => 75],
                    ['name' => 'Chicken Sandwich', 'description' => 'A crispy chicken fillet sandwich.', 'price' => 99],
                ],
                'Sides' => [
                    ['name' => 'Jolly Crispy Fries', 'description' => 'Golden, crispy fries.', 'price' => 55],
                ],
                'Desserts and Drinks' => [
                    ['name' => 'Peach Mango Pie', 'description' => 'A crisp pie filled with peach and mango.', 'price' => 49],
                    ['name' => 'Chocolate Sundae', 'description' => 'Vanilla soft serve topped with chocolate sauce.', 'price' => 39],
                    ['name' => 'Coke Float', 'description' => 'A cold cola drink topped with vanilla soft serve.', 'price' => 59],
                    ['name' => 'Iced Tea', 'description' => 'A refreshing chilled tea drink.', 'price' => 49],
                ],
            ];

            $sortOrder = 0;

            foreach ($menu as $categoryName => $menuItems) {
                $menuCategory = $store->menuCategories()->updateOrCreate(
                    ['name' => $categoryName],
                    ['sort_order' => $sortOrder],
                );

                foreach ($menuItems as $menuItem) {
                    $store->menuItems()->updateOrCreate(
                        ['name' => $menuItem['name']],
                        [
                            'menu_category_id' => $menuCategory->id,
                            'description' => $menuItem['description'],
                            'price' => $menuItem['price'],
                            'is_available' => true,
                        ],
                    );
                }

                $sortOrder++;
            }
        });

        $this->command?->info('Seeded four local/testing accounts: admin, rider, customer, and Jollibee merchant.');
    }
}
