<?php

namespace Database\Factories;

use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;


/**
 * @extends Factory<Player>
 */
class PlayerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */

    protected $model = Player::class;

    public function definition(): array
    {
        return [
            'team_id' => Player::factory(),
            'name' => fake()->name(),
            'season' => fake()->numberBetween(2024, 2026),
        ];
    }
}
