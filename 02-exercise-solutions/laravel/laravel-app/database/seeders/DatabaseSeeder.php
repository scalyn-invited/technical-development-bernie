<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\Player;
use App\Models\Venue;
use App\Models\MatchGame;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $teams = Team::factory()
            ->count(10)
            ->create();

        $venues = Venue::factory()
            ->count(3)
            ->create();

        foreach ($teams as $team) {
            Player::factory()
                ->count(15)
                ->create([
                    'team_id' => $team->id,
                    'season' => 2026,
                ]);
        }

        for ($i = 0; $i < 30; $i++) {
            $homeTeam = $teams->random();
            $awayTeam = $teams
                ->where('id', '!=', $homeTeam->id)
                ->random();

            MatchGame::factory()->create([
                'home_team_id' => $homeTeam->id,    
                'away_team_id' => $awayTeam->id,
                'venue_id' => $venues->random()->id,
            ]);
        }
    }
}
