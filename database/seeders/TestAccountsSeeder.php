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
                    'name' => 'Test Merchant',
                    'phone' => '+15555550103',
                    'password' => 'password',
                    'role' => 'merchant',
                    'status' => 'active',
                ],
            );
            $merchant->wallet()->firstOrCreate([], ['balance' => 0]);
            Store::updateOrCreate(
                ['user_id' => $merchant->id],
                [
                    'name' => 'Test Merchant Store',
                    'slug' => 'test-merchant-store',
                    'description' => 'Demo store for local testing.',
                    'category' => 'Restaurant',
                    'address' => '123 Test Street',
                    'phone' => $merchant->phone,
                    'is_open' => true,
                    'is_verified' => true,
                ],
            );
        });

        $this->command?->info('Test accounts seeded (local/testing only):');
        $this->command?->info('  Customer: customer@example.com / password');
        $this->command?->info('  Rider: rider@example.com / password');
        $this->command?->info('  Merchant: merchant@example.com / password');
    }
}
