<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_name' => $this->faker->word(),
            'price' => $this->faker->numberBetween(10, 500) / 100,
            'quantity' => $this->faker->numberBetween(1, 10),
        ];
    }
}