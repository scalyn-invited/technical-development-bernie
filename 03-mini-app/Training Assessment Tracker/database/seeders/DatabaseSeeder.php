<?php

namespace Database\Seeders;

use App\Enums\AssessmentType;
use App\Enums\PlanStatus;
use App\Enums\WeeklyEntryStatus;
use App\Models\Assessment;
use App\Models\DevelopmentPlan;
use App\Models\Skill;
use App\Models\User;
use App\Models\WeeklyEntry;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeds one programme cycle: one administrator, three members at the three
     * plan stages, and the six assessed skill domains.
     */
    public function run(): void
    {
        $administrator = User::factory()->administrator()->create([
            'name' => 'Programme Administrator',
            'email' => 'admin@example.test',
        ]);

        $skills = collect([
            ['name' => 'JavaScript Fundamentals', 'description' => 'Arrays, objects, async/await, fetch and error handling.'],
            ['name' => 'Laravel Framework', 'description' => 'Routing, controllers, requests, migrations and Eloquent.'],
            ['name' => 'REST API Design', 'description' => 'Verbs, status codes, resources and structured error responses.'],
            ['name' => 'Database and ORM', 'description' => 'Relational modelling, eager loading, N+1 and indexes.'],
            ['name' => 'Automated Testing', 'description' => 'Feature and unit tests with Pest or PHPUnit.'],
            ['name' => 'Modern App Development', 'description' => 'Git discipline, tooling, CI and deployment.'],
        ])->map(fn (array $attributes) => Skill::factory()->create($attributes + [
            'created_by' => $administrator->id,
        ]));

        // A retired skill, kept to prove deactivation is the retirement path.
        Skill::factory()->inactive()->create([
            'name' => 'Legacy jQuery Maintenance',
            'description' => 'Retired from the current cycle; never deleted, because scores reference it.',
            'created_by' => $administrator->id,
        ]);

        $this->seedDraftPlan($administrator, $skills);
        $this->seedActivePlan($administrator, $skills);
        $this->seedCompletedPlan($administrator, $skills);
    }

    /** Stage 1: baselines partly recorded, nothing activated yet. */
    private function seedDraftPlan(User $administrator, $skills): void
    {
        $member = User::factory()->create([
            'name' => 'Member In Draft',
            'email' => 'draft.member@example.test',
        ]);

        $plan = DevelopmentPlan::factory()->create([
            'user_id' => $member->id,
            'created_by' => $administrator->id,
            'status' => PlanStatus::Draft,
            'key_gaps' => 'Async control flow and API error handling.',
            'weekly_focus' => 'One JavaScript domain per week, evidenced by committed exercises.',
        ]);

        // Deliberately incomplete: three of six skills baselined, so the
        // activation rule has something real to refuse.
        foreach ($skills->take(3) as $skill) {
            Assessment::factory()->baseline()->create([
                'development_plan_id' => $plan->id,
                'skill_id' => $skill->id,
                'recorded_by' => $administrator->id,
            ]);
        }
    }

    /** Stage 2: fully baselined, activated, weeks 1-2 closed and week 3 open. */
    private function seedActivePlan(User $administrator, $skills): void
    {
        $member = User::factory()->create([
            'name' => 'Member In Progress',
            'email' => 'active.member@example.test',
        ]);

        $plan = DevelopmentPlan::factory()->active()->create([
            'user_id' => $member->id,
            'created_by' => $administrator->id,
            'key_gaps' => 'Database reasoning and automated testing.',
            'weekly_focus' => 'Query counts measured before and after every change.',
        ]);

        foreach ($skills as $skill) {
            Assessment::factory()->baseline()->create([
                'development_plan_id' => $plan->id,
                'skill_id' => $skill->id,
                'recorded_by' => $administrator->id,
            ]);
        }

        foreach ([1, 2] as $week) {
            WeeklyEntry::factory()->closed()->create([
                'development_plan_id' => $plan->id,
                'week_number' => $week,
                'skill_id' => $skills[$week - 1]->id,
                'recorded_by' => $administrator->id,
            ]);
        }

        WeeklyEntry::factory()->evidenced()->create([
            'development_plan_id' => $plan->id,
            'week_number' => 3,
            'skill_id' => $skills[2]->id,
            'recorded_by' => $administrator->id,
        ]);
    }

    /** Stage 3: the full cycle, baseline through final, ready to compare. */
    private function seedCompletedPlan(User $administrator, $skills): void
    {
        $member = User::factory()->create([
            'name' => 'Member Completed',
            'email' => 'completed.member@example.test',
        ]);

        $plan = DevelopmentPlan::factory()->completed()->create([
            'user_id' => $member->id,
            'created_by' => $administrator->id,
            'key_gaps' => 'Authorisation modelling and deployment.',
            'weekly_focus' => 'One shipped, reviewable increment per week.',
        ]);

        foreach ($skills as $index => $skill) {
            foreach ([AssessmentType::Baseline, AssessmentType::Final] as $type) {
                Assessment::factory()
                    ->{$type->value}()
                    ->create([
                        'development_plan_id' => $plan->id,
                        'skill_id' => $skill->id,
                        'recorded_by' => $administrator->id,
                    ]);
            }

            WeeklyEntry::factory()->closed()->create([
                'development_plan_id' => $plan->id,
                'week_number' => $index + 1,
                'skill_id' => $skill->id,
                'recorded_by' => $administrator->id,
            ]);
        }
    }
}
