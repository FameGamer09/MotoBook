<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();

            $table->foreignId('customer_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->foreignId('rider_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            $table->foreignId('merchant_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            $table->decimal('merchant_lat', 11, 8)->nullable();
            $table->decimal('merchant_lng', 11, 8)->nullable();
            $table->string('pickup_address')->nullable();

            $table->decimal('customer_lat', 11, 8);
            $table->decimal('customer_lng', 11, 8);
            $table->string('dropoff_address');

            $table->decimal('rider_lat', 11, 8)->nullable();
            $table->decimal('rider_lng', 11, 8)->nullable();

            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->string('payment_method')->default('COD');
            $table->string('payment_status')->default('UNPAID');
            $table->string('payment_reference')->nullable();

            $table->text('delivery_notes')->nullable();
            $table->string('customer_signature')->nullable();

            $table->string('status')
                ->default('PENDING')
                ->comment('PENDING, OFFER_RECEIVED, ACCEPTED, NAVIGATING_TO_PICKUP, ARRIVED_AT_PICKUP, ORDER_VERIFIED, NAVIGATING_TO_DROP_OFF, ARRIVED_AT_DROP_OFF, PROOF_SUBMITTED, COMPLETED, CANCELLED');

            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'rider_id']);
            $table->index(['status', 'customer_id']);
            $table->index(['rider_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
