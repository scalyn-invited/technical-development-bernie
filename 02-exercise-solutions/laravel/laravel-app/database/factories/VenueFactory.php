<?php

namespace Database\Factories;

use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Venue>
 */
class VenueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */

    protected $model = Venue::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company() . ' Arena',
            'address' => fake()->address(),
        ];
    }
}
