<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('customer_settings')->nullable();
        });

        Schema::create('customer_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'store_id']);
        });

        Schema::create('customer_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40)->default('order');
            $table->string('title', 160);
            $table->text('message');
            $table->string('action_url')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('custom_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rider_id')->nullable()->constrained('riders')->nullOnDelete();
            $table->string('pickup_address');
            $table->string('pickup_landmark')->nullable();
            $table->string('sender_name');
            $table->string('sender_phone', 30);
            $table->decimal('pickup_latitude', 10, 7)->nullable();
            $table->decimal('pickup_longitude', 10, 7)->nullable();
            $table->string('dropoff_address');
            $table->string('dropoff_landmark')->nullable();
            $table->string('recipient_name');
            $table->string('recipient_phone', 30);
            $table->decimal('dropoff_latitude', 10, 7)->nullable();
            $table->decimal('dropoff_longitude', 10, 7)->nullable();
            $table->string('package_category', 40);
            $table->text('description');
            $table->decimal('delivery_fee', 10, 2)->default(250);
            $table->decimal('service_fee', 10, 2)->default(15);
            $table->decimal('total_amount', 10, 2);
            $table->string('payment_method', 30)->default('cod');
            $table->string('payment_status', 30)->default('unpaid');
            $table->string('status', 40)->default('pending_assignment')->index();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['rider_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_deliveries');
        Schema::dropIfExists('customer_notifications');
        Schema::dropIfExists('customer_favorites');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('customer_settings');
        });
    }
};
