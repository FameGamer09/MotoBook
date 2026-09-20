<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        // Create cashier user
        $cashier = User::create([
            'name' => 'Cashier',
            'email' => 'cashier@example.com',
            'password' => bcrypt('password'),
            'role' => 'cashier',
        ]);

        // Create categories
        $categories = [
            ['name' => 'Electronics', 'description' => 'Electronic devices and accessories'],
            ['name' => 'Clothing', 'description' => 'Apparel and fashion items'],
            ['name' => 'Food & Beverages', 'description' => 'Food products and drinks'],
            ['name' => 'Office Supplies', 'description' => 'Office and stationery items'],
            ['name' => 'Home & Garden', 'description' => 'Home improvement and garden items'],
        ];

        foreach ($categories as $categoryData) {
            Category::create($categoryData);
        }

        // Create sample products
        $products = [
            ['name' => 'Wireless Mouse', 'sku' => 'SKU-001-WM', 'price' => 29.99, 'cost' => 15.00, 'quantity' => 50, 'category_id' => 1],
            ['name' => 'USB Keyboard', 'sku' => 'SKU-002-UK', 'price' => 49.99, 'cost' => 25.00, 'quantity' => 30, 'category_id' => 1],
            ['name' => 'HDMI Cable', 'sku' => 'SKU-003-HC', 'price' => 15.99, 'cost' => 5.00, 'quantity' => 100, 'category_id' => 1],
            ['name' => 'Cotton T-Shirt', 'sku' => 'SKU-004-CT', 'price' => 19.99, 'cost' => 8.00, 'quantity' => 75, 'category_id' => 2],
            ['name' => 'Denim Jeans', 'sku' => 'SKU-005-DJ', 'price' => 59.99, 'cost' => 30.00, 'quantity' => 25, 'category_id' => 2],
            ['name' => 'Coffee Beans 1kg', 'sku' => 'SKU-006-CB', 'price' => 24.99, 'cost' => 12.00, 'quantity' => 40, 'category_id' => 3],
            ['name' => 'Green Tea Box', 'sku' => 'SKU-007-GT', 'price' => 12.99, 'cost' => 5.00, 'quantity' => 60, 'category_id' => 3],
            ['name' => 'Notebook Pack', 'sku' => 'SKU-008-NB', 'price' => 8.99, 'cost' => 3.00, 'quantity' => 200, 'category_id' => 4],
            ['name' => 'Pen Set (10pcs)', 'sku' => 'SKU-009-PS', 'price' => 5.99, 'cost' => 2.00, 'quantity' => 150, 'category_id' => 4],
            ['name' => 'Plant Pot', 'sku' => 'SKU-010-PP', 'price' => 14.99, 'cost' => 6.00, 'quantity' => 35, 'category_id' => 5],
        ];

        foreach ($products as $productData) {
            $product = Product::create($productData);

            // Create initial inventory movement
            if ($product->quantity > 0) {
                $product->inventoryMovements()->create([
                    'user_id' => $admin->id,
                    'type' => 'in',
                    'quantity' => $product->quantity,
                    'previous_quantity' => 0,
                    'new_quantity' => $product->quantity,
                    'notes' => 'Initial stock',
                ]);
            }
        }

        $this->command->info('Database seeded successfully!');
        $this->command->info('Login credentials:');
        $this->command->info('  Admin: admin@example.com / password');
        $this->command->info('  Cashier: cashier@example.com / password');
    }
}
