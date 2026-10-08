<?php

namespace Database\Factories;

use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    public function definition(): array
    {
        $items = [
            'Dark Choco Frappe' => 150, 'Iced Matcha Latte' => 130, 'Classic Cheeseburger' => 145,
            'Sizzling Sisig' => 180, 'Pancit Canton' => 120, 'Lechon Kawali' => 220,
            'Buttered Waffle' => 95, 'Okinawa Milk Tea' => 100, 'Chicken Inasal' => 165,
            'Halo-Halo Special' => 110, 'Garlic Fried Rice' => 45, 'Grilled Liempo' => 195,
        ];

        $name = fake()->randomElement(array_keys($items));

        return [
            'name' => $name,
            'description' => fake()->sentence(8),
            'price' => $items[$name],
            'is_available' => true,
        ];
    }
}
