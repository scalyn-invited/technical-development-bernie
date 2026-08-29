<?php

namespace Database\Factories;

use App\Models\MatchGame;
use App\Models\Team;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MatchModel>
 */
class MatchGameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = MatchGame::class;

    public function definition(): array
    {
        return [
            'home_team_id' => Team::factory(),
            'away_team_id' => Team::factory(),

            'venue_id' => Venue::factory(),

            'scheduled_at' => fake()->dateTimeBetween(
                'now',
                '+3 months'
            ),

            'home_score' => null,
            'away_score' => null,
            'winner_team_id' => null,
            'notes' => null,
        ];
    }
}