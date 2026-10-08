<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('vehicle_type', ['motorcycle', 'bicycle', 'car'])->default('motorcycle');
            $table->string('plate_number')->nullable();
            $table->string('license_number')->nullable();
            $table->string('license_photo')->nullable();
            $table->enum('status', ['offline', 'online', 'busy'])->default('offline')->index();
            $table->decimal('current_latitude', 10, 7)->nullable();
            $table->decimal('current_longitude', 10, 7)->nullable();
            $table->decimal('rating', 2, 1)->default(0);
            $table->unsignedInteger('level')->default(1);
            $table->unsignedInteger('total_deliveries')->default(0);
            $table->decimal('acceptance_rate', 5, 2)->default(100);
            $table->decimal('cancellation_rate', 5, 2)->default(0);
            $table->boolean('is_verified')->default(false);
            $table->timestamps();

            $table->index(['current_latitude', 'current_longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riders');
    }
};
