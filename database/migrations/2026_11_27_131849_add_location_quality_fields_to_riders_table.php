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
        Schema::table('riders', function (Blueprint $table) {
            $table->decimal('current_location_accuracy_m', 7, 2)->nullable()->after('current_longitude');
            $table->timestamp('current_location_updated_at')->nullable()->after('current_location_accuracy_m')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            $table->dropIndex(['current_location_updated_at']);
            $table->dropColumn(['current_location_accuracy_m', 'current_location_updated_at']);
        });
    }
};
