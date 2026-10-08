<?php

namespace Database\Factories;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Big Brew Coffee', 'Grill House PH', 'Noodle Bar', 'Burger Barn',
            'Mang Kanor\'s Grill', 'Kusina ni Aling Rosa', 'Choco Haven',
            'Roxas Seafood Grill', 'Panciteria Antigua', 'The Waffle Stop',
        ]);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(5),
            'category' => fake()->randomElement(['Fast Food', 'Cafe', 'Restaurant', 'Bakery']),
            'description' => fake()->sentence(12),
            'phone' => '09'.fake()->numerify('#########'),
            'address' => fake()->streetAddress().', Roxas City, Capiz',
            'latitude' => 11.5850 + fake()->randomFloat(5, -0.03, 0.03),
            'longitude' => 122.7511 + fake()->randomFloat(5, -0.03, 0.03),
            'is_open' => true,
            'is_verified' => true,
            'rating' => fake()->randomFloat(1, 3.8, 5.0),
            'commission_rate' => 15,
        ];
    }
}
