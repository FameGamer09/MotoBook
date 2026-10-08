<?php

namespace Database\Factories;

use App\Models\Rider;
use Illuminate\Database\Eloquent\Factories\Factory;

class RiderProfileFactory extends Factory
{
    protected $model = Rider::class;

    public function definition(): array
    {
        return [
            'vehicle_type' => 'motorcycle',
            'plate_number' => strtoupper(fake()->bothify('???-####')),
            'license_number' => fake()->numerify('N##-##-######'),
            'status' => fake()->randomElement(['online', 'online', 'offline']),
            'current_latitude' => 11.5850 + fake()->randomFloat(5, -0.03, 0.03),
            'current_longitude' => 122.7511 + fake()->randomFloat(5, -0.03, 0.03),
            'rating' => fake()->randomFloat(1, 4.0, 5.0),
            'level' => fake()->numberBetween(1, 5),
            'total_deliveries' => fake()->numberBetween(20, 500),
            'acceptance_rate' => fake()->randomFloat(2, 85, 100),
            'cancellation_rate' => fake()->randomFloat(2, 0, 5),
            'is_verified' => true,
        ];
    }
}
