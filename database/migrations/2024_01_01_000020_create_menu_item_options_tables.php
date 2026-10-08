<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_item_option_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g. "Choose your Drink", "Choose your Drink Size"
            $table->enum('selection_type', ['single', 'multiple'])->default('single');
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('max_selections')->nullable(); // null = unlimited, only relevant for 'multiple'
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('menu_item_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_option_group_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g. "Coke Regular", "Large", "Extra Gravy"
            $table->decimal('price_delta', 8, 2)->default(0); // added to base item price when selected
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_options');
        Schema::dropIfExists('menu_item_option_groups');
    }
};
