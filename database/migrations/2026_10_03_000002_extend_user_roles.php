<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'cashier', 'customer', 'rider') NOT NULL DEFAULT 'customer'");

            return;
        }
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 50)->default('customer')->change();
        });
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'cashier') NOT NULL DEFAULT 'cashier'");

            return;
        }
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 50)->default('cashier')->change();
        });
    }
};
