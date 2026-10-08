<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->unsignedBigInteger('management_store_id')->nullable()->unique();
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->unsignedBigInteger('management_menu_item_id')->nullable()->unique();
        });

        Schema::table('menu_item_option_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('management_option_group_id')->nullable()->unique();
        });

        Schema::table('menu_item_options', function (Blueprint $table) {
            $table->unsignedBigInteger('management_option_id')->nullable()->unique();
            $table->boolean('is_available')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('menu_item_options', function (Blueprint $table) {
            $table->dropUnique(['management_option_id']);
            $table->dropColumn(['management_option_id', 'is_available']);
        });

        Schema::table('menu_item_option_groups', function (Blueprint $table) {
            $table->dropUnique(['management_option_group_id']);
            $table->dropColumn('management_option_group_id');
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropUnique(['management_menu_item_id']);
            $table->dropColumn('management_menu_item_id');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropUnique(['management_store_id']);
            $table->dropColumn('management_store_id');
        });
    }
};
