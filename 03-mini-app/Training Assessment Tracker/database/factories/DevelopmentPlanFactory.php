<?php

namespace Database\Factories;

use App\Enums\PlanStatus;
use App\Models\DevelopmentPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DevelopmentPlan>
 */
class DevelopmentPlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'key_gaps' => fake()->sentence(10),
            'weekly_focus' => fake()->sentence(8),
            'status' => PlanStatus::Draft,
            'activated_at' => null,
            'completed_at' => null,
            'created_by' => User::factory()->administrator(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PlanStatus::Active,
            'activated_at' => now()->subWeeks(2),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PlanStatus::Completed,
            'activated_at' => now()->subWeeks(4),
            'completed_at' => now(),
        ]);
    }
}
