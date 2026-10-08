<?php

namespace Database\Factories;

use App\Models\MenuCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class MenuCategoryFactory extends Factory
{
    protected $model = MenuCategory::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Best Sellers', 'Main Dishes', 'Drinks', 'Desserts', 'Sides']),
            'sort_order' => fake()->numberBetween(0, 5),
        ];
    }
}
