<?php

namespace Database\Factories;

use App\Enums\AssessmentType;
use App\Models\Assessment;
use App\Models\DevelopmentPlan;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assessment>
 */
class AssessmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'development_plan_id' => DevelopmentPlan::factory(),
            'skill_id' => Skill::factory(),
            'type' => AssessmentType::Baseline,
            'score' => fake()->randomFloat(2, 30, 60),
            'note' => fake()->optional()->sentence(),
            'recorded_by' => User::factory()->administrator(),
            'recorded_at' => now(),
        ];
    }

    public function baseline(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AssessmentType::Baseline,
            'score' => fake()->randomFloat(2, 30, 60),
        ]);
    }

    public function final(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AssessmentType::Final,
            'score' => fake()->randomFloat(2, 70, 95),
        ]);
    }
}
