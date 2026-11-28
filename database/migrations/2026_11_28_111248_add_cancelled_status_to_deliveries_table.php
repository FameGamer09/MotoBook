<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->enum('status', [
                'pending_assignment',
                'assigned',
                'accepted',
                'rejected',
                'en_route_to_pickup',
                'arrived_at_pickup',
                'picked_up',
                'en_route_to_customer',
                'arrived_at_customer',
                'delivered',
                'failed',
                'cancelled',
            ])->default('pending_assignment')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->enum('status', [
                'pending_assignment',
                'assigned',
                'accepted',
                'rejected',
                'en_route_to_pickup',
                'arrived_at_pickup',
                'picked_up',
                'en_route_to_customer',
                'arrived_at_customer',
                'delivered',
                'failed',
            ])->default('pending_assignment')->change();
        });
    }
};
