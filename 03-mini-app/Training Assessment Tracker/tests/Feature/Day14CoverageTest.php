<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\DevelopmentPlan;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Day14CoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_cannot_complete_even_with_matching_final_set(): void
    {
        Sanctum::actingAs(User::factory()->administrator()->create());
        $baseline = Assessment::factory()->create();
        $this->postJson("/api/plans/{$baseline->development_plan_id}/complete", [
            'finals' => [['skill_id' => $baseline->skill_id, 'score' => 90]],
        ])->assertStatus(409)->assertJsonPath('code', 'progression_conflict');
        $this->assertSame('draft', $baseline->developmentPlan->fresh()->status->value);
        $this->assertDatabaseMissing('assessments', ['development_plan_id' => $baseline->development_plan_id, 'type' => 'final']);
    }

    public function test_member_and_own_plan_administrator_cannot_record_scores(): void
    {
        $plan = DevelopmentPlan::factory()->create();
        $payload = ['skill_id' => Skill::factory()->create()->id, 'type' => 'baseline', 'score' => 50];
        Sanctum::actingAs($plan->member);
        $this->postJson("/api/plans/{$plan->id}/assessments", $payload)->assertForbidden();
        $admin = User::factory()->administrator()->create();
        $plan->update(['user_id' => $admin->id]);
        Sanctum::actingAs($admin);
        $this->postJson("/api/plans/{$plan->id}/assessments", $payload)->assertForbidden();
        $this->assertSame(0, $plan->assessments()->count());
    }

    public function test_baseline_creation_to_completed_comparison_through_http(): void
    {
        Sanctum::actingAs(User::factory()->administrator()->create());
        $plan = DevelopmentPlan::factory()->create();
        $skill = Skill::factory()->create();
        $root = "/api/plans/{$plan->id}";
        $this->postJson("$root/activate")->assertStatus(409);
        $this->postJson("$root/assessments", [
            'skill_id' => $skill->id, 'type' => 'baseline', 'score' => 60.25,
        ])->assertCreated();
        $this->postJson("$root/activate")->assertOk();
        $id = $this->postJson("$root/weeks", [
            'skill_id' => $skill->id, 'week_number' => 1, 'objective' => 'Exercise',
        ])->assertCreated()->json('data.id');
        $this->patchJson("$root/weeks/$id", ['status' => 'evidenced', 'evidence' => 'Test output'])->assertOk();
        $this->patchJson("$root/weeks/$id", ['status' => 'closed', 'outcome_score' => 85.10])->assertOk();
        $this->postJson("$root/complete", ['finals' => [['skill_id' => $skill->id, 'score' => 85.10]]])->assertOk();
        $this->getJson("$root/comparison")->assertOk()
            ->assertJsonPath('data.0.delta', '24.85')
            ->assertJsonPath('summary.average_movement', '24.85')
            ->assertJsonPath('summary.compared_skills', 1)
            ->assertJsonPath('summary.pending_skills', 0);
    }

    public function test_partial_comparison_exposes_denominator_without_treating_missing_final_as_zero(): void
    {
        Sanctum::actingAs(User::factory()->administrator()->create());
        $plan = DevelopmentPlan::factory()->create();
        $first = Assessment::factory()->create(['development_plan_id' => $plan->id, 'score' => 50]);
        Assessment::factory()->create(['development_plan_id' => $plan->id, 'score' => 100]);
        Assessment::factory()->final()->create([
            'development_plan_id' => $plan->id, 'skill_id' => $first->skill_id, 'score' => 60,
        ]);
        $this->getJson("/api/plans/{$plan->id}/comparison")->assertOk()
            ->assertJsonPath('summary.average_movement', '10.00')
            ->assertJsonPath('summary.compared_skills', 1)
            ->assertJsonPath('summary.pending_skills', 1);
    }
}
