<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('rider_id')->nullable()->constrained('riders')->nullOnDelete();

            $table->enum('status', [
                'pending_assignment', // "Assign Nearest Available Rider"
                'assigned',           // pushed to rider, awaiting accept/reject
                'accepted',
                'rejected',           // rider rejected -> "Return to Available Riders"
                'en_route_to_pickup',
                'arrived_at_pickup',
                'picked_up',
                'en_route_to_customer',
                'arrived_at_customer',
                'delivered',
                'failed',
            ])->default('pending_assignment')->index();

            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->decimal('distance_km', 6, 2)->nullable();
            $table->unsignedInteger('eta_minutes')->nullable();
            $table->string('proof_photo')->nullable();
            $table->string('delivery_otp', 6)->nullable();

            // earnings breakdown - matches Rider App "Earnings" screen
            $table->decimal('base_fare', 8, 2)->default(0);
            $table->decimal('incentive', 8, 2)->default(0);
            $table->decimal('tip', 8, 2)->default(0);
            $table->decimal('total_earning', 8, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
