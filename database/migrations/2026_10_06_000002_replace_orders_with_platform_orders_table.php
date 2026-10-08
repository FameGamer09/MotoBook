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
        Schema::dropIfExists('orders');

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rider_id')->nullable()->constrained('riders')->nullOnDelete();
            $table->foreignId('delivery_address_id')->nullable()->constrained('addresses')->nullOnDelete();
            $table->json('delivery_address_snapshot')->nullable();
            $table->enum('status', [
                'pending',
                'accepted',
                'rejected',
                'preparing',
                'ready_for_pickup',
                'assigned',
                'out_for_delivery',
                'delivered',
                'completed',
                'cancelled',
            ])->default('pending')->index();
            $table->enum('payment_method', ['cod', 'wallet', 'card', 'gcash'])->default('cod');
            $table->enum('payment_status', ['unpaid', 'paid', 'refunded'])->default('unpaid');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('delivery_fee', 8, 2)->default(0);
            $table->decimal('service_fee', 8, 2)->default(0);
            $table->decimal('discount_amount', 8, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->text('customer_note')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->foreignId('merchant_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('merchant_lat', 11, 8)->nullable();
            $table->decimal('merchant_lng', 11, 8)->nullable();
            $table->string('pickup_address')->nullable();
            $table->decimal('customer_lat', 11, 8)->nullable();
            $table->decimal('customer_lng', 11, 8)->nullable();
            $table->string('dropoff_address')->nullable();
            $table->decimal('rider_lat', 11, 8)->nullable();
            $table->decimal('rider_lng', 11, 8)->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->string('customer_signature')->nullable();

            $table->timestamps();
            $table->index(['store_id', 'status']);
            $table->index(['customer_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('rider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('merchant_id')->nullable()->constrained('users')->nullOnDelete();
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
            $table->string('status')->default('PENDING');
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
};
