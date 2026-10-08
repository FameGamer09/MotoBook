<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        if (! Schema::connection('management')->hasColumn('orders', 'google_maps_location')) {
            Schema::connection('management')->table('orders', function (Blueprint $table) {
                $table->text('google_maps_location')->nullable()->after('delivery_address');
            });
        }
    }

    public function down(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        if (Schema::connection('management')->hasColumn('orders', 'google_maps_location')) {
            Schema::connection('management')->table('orders', function (Blueprint $table) {
                $table->dropColumn('google_maps_location');
            });
        }
    }
};
