<?php

namespace Database\Factories;

use App\Enums\WeeklyEntryStatus;
use App\Models\DevelopmentPlan;
use App\Models\Skill;
use App\Models\User;
use App\Models\WeeklyEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeeklyEntry>
 */
class WeeklyEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'development_plan_id' => DevelopmentPlan::factory(),
            'week_number' => 1,
            'skill_id' => Skill::factory(),
            'objective' => fake()->sentence(8),
            'evidence' => null,
            'outcome_score' => null,
            'status' => WeeklyEntryStatus::Planned,
            'recorded_by' => User::factory()->administrator(),
            'closed_at' => null,
        ];
    }

    public function evidenced(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WeeklyEntryStatus::Evidenced,
            'evidence' => fake()->sentence(12),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WeeklyEntryStatus::Closed,
            'evidence' => fake()->sentence(12),
            'outcome_score' => fake()->randomFloat(2, 55, 90),
            'closed_at' => now(),
        ]);
    }
}
