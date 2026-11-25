<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('The demo database seeder may only run in local or testing environments.');
        }

        $this->call(TestAccountsSeeder::class);

        $this->command?->info('Database seeded successfully!');
        $this->command?->info('Login credentials:');
        $this->command?->info('  Admin: admin@example.com / password');
        $this->command?->info('  Rider: rider@example.com / password');
        $this->command?->info('  Customer: customer@example.com / password');
        $this->command?->info('  Jollibee Merchant: merchant@example.com / password');
    }
}
