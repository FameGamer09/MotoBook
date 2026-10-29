<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('The demo database seeder may only run in local or testing environments.');
        }

        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => 'password',
                'role' => 'admin',
            ],
        );

        User::updateOrCreate(
            ['email' => 'cashier@example.com'],
            [
                'name' => 'Cashier',
                'password' => 'password',
                'role' => 'cashier',
            ],
        );

        $categories = [
            ['name' => 'Electronics', 'description' => 'Electronic devices and accessories'],
            ['name' => 'Clothing', 'description' => 'Apparel and fashion items'],
            ['name' => 'Food & Beverages', 'description' => 'Food products and drinks'],
            ['name' => 'Office Supplies', 'description' => 'Office and stationery items'],
            ['name' => 'Home & Garden', 'description' => 'Home improvement and garden items'],
        ];

        $categoryIds = [];

        foreach ($categories as $categoryData) {
            $category = Category::firstOrCreate(
                ['name' => $categoryData['name']],
                ['description' => $categoryData['description']],
            );
            $categoryIds[$categoryData['name']] = $category->id;
        }

        $products = [
            ['name' => 'Wireless Mouse', 'sku' => 'SKU-001-WM', 'price' => 29.99, 'cost' => 15.00, 'quantity' => 50, 'category' => 'Electronics'],
            ['name' => 'USB Keyboard', 'sku' => 'SKU-002-UK', 'price' => 49.99, 'cost' => 25.00, 'quantity' => 30, 'category' => 'Electronics'],
            ['name' => 'HDMI Cable', 'sku' => 'SKU-003-HC', 'price' => 15.99, 'cost' => 5.00, 'quantity' => 100, 'category' => 'Electronics'],
            ['name' => 'Cotton T-Shirt', 'sku' => 'SKU-004-CT', 'price' => 19.99, 'cost' => 8.00, 'quantity' => 75, 'category' => 'Clothing'],
            ['name' => 'Denim Jeans', 'sku' => 'SKU-005-DJ', 'price' => 59.99, 'cost' => 30.00, 'quantity' => 25, 'category' => 'Clothing'],
            ['name' => 'Coffee Beans 1kg', 'sku' => 'SKU-006-CB', 'price' => 24.99, 'cost' => 12.00, 'quantity' => 40, 'category' => 'Food & Beverages'],
            ['name' => 'Green Tea Box', 'sku' => 'SKU-007-GT', 'price' => 12.99, 'cost' => 5.00, 'quantity' => 60, 'category' => 'Food & Beverages'],
            ['name' => 'Notebook Pack', 'sku' => 'SKU-008-NB', 'price' => 8.99, 'cost' => 3.00, 'quantity' => 200, 'category' => 'Office Supplies'],
            ['name' => 'Pen Set (10pcs)', 'sku' => 'SKU-009-PS', 'price' => 5.99, 'cost' => 2.00, 'quantity' => 150, 'category' => 'Office Supplies'],
            ['name' => 'Plant Pot', 'sku' => 'SKU-010-PP', 'price' => 14.99, 'cost' => 6.00, 'quantity' => 35, 'category' => 'Home & Garden'],
        ];

        foreach ($products as $productData) {
            $categoryName = $productData['category'];
            unset($productData['category']);
            $productData['category_id'] = $categoryIds[$categoryName];
            $product = Product::firstOrCreate(
                ['sku' => $productData['sku']],
                $productData,
            );

            if ($product->quantity > 0) {
                $product->inventoryMovements()->firstOrCreate([
                    'user_id' => $admin->id,
                    'type' => 'in',
                    'notes' => 'Initial stock',
                ], [
                    'quantity' => $product->quantity,
                    'previous_quantity' => 0,
                    'new_quantity' => $product->quantity,
                ]);
            }
        }

        $this->call(TestAccountsSeeder::class);

        $this->command->info('Database seeded successfully!');
        $this->command->info('Login credentials:');
        $this->command->info('  Admin: admin@example.com / password');
        $this->command->info('  Cashier: cashier@example.com / password');
    }
}
