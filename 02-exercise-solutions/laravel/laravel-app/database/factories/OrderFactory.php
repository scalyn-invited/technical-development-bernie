<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'total' => $this->faker->numberBetween(50, 5000) / 100,
            'status' => $this->faker->randomElement(['pending', 'completed', 'cancelled']),
            'placed_at' => $this->faker->dateTimeBetween('-30 days'),
        ];
    }
}