<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'invoice_number' => $this->generateInvoiceNumber(),
            'subtotal' => fake()->randomFloat(2, 10, 1000),
            'tax' => fake()->randomFloat(2, 0, 100),
            'discount' => fake()->randomFloat(2, 0, 50),
            'total' => fake()->randomFloat(2, 10, 1100),
            'status' => 'completed',
            'payment_method' => fake()->randomElement(['cash', 'card', 'digital']),
            'notes' => null,
        ];
    }

    private function generateInvoiceNumber(): string
    {
        $prefix = 'INV-' . date('Ymd');
        $newNumber = fake()->unique()->numberBetween(1, 99999);
        return $prefix . '-' . str_pad($newNumber, 5, '0', STR_PAD_LEFT);
    }
}