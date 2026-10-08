<?php

namespace Database\Factories;

use App\Models\Address;
use Illuminate\Database\Eloquent\Factories\Factory;

class AddressFactory extends Factory
{
    protected $model = Address::class;

    public function definition(): array
    {
        return [
            'label' => fake()->randomElement(['Home', 'Work', 'Condo']),
            'address_line' => fake()->streetAddress().', Roxas City, Capiz',
            'landmark' => fake()->randomElement(['Near city plaza', 'Beside 7-Eleven', 'Across the school', null]),
            'latitude' => 11.5850 + fake()->randomFloat(5, -0.02, 0.02),
            'longitude' => 122.7511 + fake()->randomFloat(5, -0.02, 0.02),
            'is_default' => true,
        ];
    }
}
