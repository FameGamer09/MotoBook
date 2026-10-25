<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class MotobookCheck extends Command
{
    protected $signature = 'motobook:check';

    protected $description = 'Check MotoBook configuration and connected system services without changing data';

    private int $failures = 0;

    public function handle(): int
    {
        $this->check('Application key', (bool) config('app.key'), 'APP_KEY is missing; run php artisan key:generate.');
        $this->check('Primary database', $this->canQuery('mysql'), 'Check DB_* settings and ensure MySQL is running.');
        $this->check('Primary migrations', $this->migrationsAreCurrent(), 'Run php artisan migrate after confirming the primary database name.');
        $this->check('Management database', $this->canQuery('management'), 'Check MANAGEMENT_DB_* settings and import admin/database/schema.sql plus admin/database/operations.sql.');

        foreach (['orders', 'riders'] as $table) {
            $exists = $this->tableExists('management', $table);
            $this->check("Management table: {$table}", $exists, 'The management schema is incomplete.');
        }

        $this->check('Rider telemetry schema', $this->tableExists('management', 'rider_location_logs'), 'Run A-rider/migrations/001_init_rider_schema.sql on motobook_admin.');
        $this->check('Writable storage', is_writable(storage_path()) && is_writable(storage_path('logs')), 'Grant the PHP process write access to storage/ and storage/logs/.');

        if (! config('services.semaphore.api_key')) {
            $this->warn('SMS provider: not configured (set SEMAPHORE_API_KEY to enable SMS OTP).');
        } else {
            $this->info('SMS provider: credentials are present; live delivery still requires a valid Semaphore account and balance.');
        }

        if (! class_exists(Socialite::class)) {
            $this->warn('Social login: Socialite is not installed; run composer install from a current composer.lock or resolve Composer dependencies.');
        } else {
            foreach (['google', 'facebook', 'apple'] as $provider) {
                if (! config("services.{$provider}.client_id") || ! config("services.{$provider}.client_secret")) {
                    $this->warn("Social login: {$provider} credentials are missing; configure its client credentials and callback URL before enabling it.");
                } else {
                    $this->info("Social login: {$provider} credentials are present; provider callback and live sign-in still need verification.");
                }
            }
        }

        if ($this->failures > 0) {
            $this->error("Found {$this->failures} required setup issue(s).");

            return self::FAILURE;
        }

        $this->info('Required local application and database checks passed. Provider and device checks may still be needed.');

        return self::SUCCESS;
    }

    private function check(string $label, bool $passed, string $hint): void
    {
        if ($passed) {
            $this->line("<fg=green>PASS</> {$label}");

            return;
        }

        $this->line("<fg=red>FAIL</> {$label} — {$hint}");
        $this->failures++;
    }

    private function canQuery(string $connection): bool
    {
        try {
            DB::connection($connection)->select('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function tableExists(string $connection, string $table): bool
    {
        try {
            return Schema::connection($connection)->hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    private function migrationsAreCurrent(): bool
    {
        try {
            if (! Schema::hasTable('migrations')) {
                return false;
            }

            $applied = DB::table('migrations')->pluck('migration')->all();
            $files = collect(glob(database_path('migrations/*.php')) ?: [])
                ->map(fn (string $file) => pathinfo($file, PATHINFO_FILENAME));

            return $files->diff($applied)->isEmpty();
        } catch (Throwable) {
            return false;
        }
    }
}
