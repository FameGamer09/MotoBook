<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->unsignedInteger('calories')->nullable()->after('price');
            $table->unsignedInteger('prep_time_minutes')->nullable()->after('calories');
            $table->string('highlight_badge')->nullable()->after('prep_time_minutes'); // e.g. "High Protein"
        });

        Schema::table('order_items', function (Blueprint $table) {
            // snapshot of selected options at order time, for receipt/history accuracy
            // even if the merchant later changes/deletes an option
            $table->json('options_snapshot')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn(['calories', 'prep_time_minutes', 'highlight_badge']);
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('options_snapshot');
        });
    }
};
