<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\DevelopmentPlan;
use App\Models\Skill;
use App\Models\User;
use App\Models\WeeklyEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlanProgressionTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Sanctum::actingAs(User::factory()->administrator()->create());
        $plan = DevelopmentPlan::factory()->create();
        $baseline = Assessment::factory()->create(['development_plan_id' => $plan->id, 'score' => 0]);

        return [$plan, $baseline];
    }

    public function test_activation_accepts_zero_and_freezes_membership(): void
    {
        [$plan, $baseline] = $this->fixture();
        $this->postJson("/api/plans/{$plan->id}/activate")->assertOk()->assertJsonPath('data.status', 'active');
        $this->assertNotNull($plan->fresh()->activated_at);
        $this->postJson("/api/plans/{$plan->id}/activate")->assertStatus(409)->assertJsonPath('code', 'progression_conflict');
        $this->postJson("/api/plans/{$plan->id}/assessments", [
            'skill_id' => Skill::factory()->create()->id, 'type' => 'baseline', 'score' => 50,
        ])->assertStatus(409);
        $this->assertSame(1, $plan->baselineAssessments()->count());
        $this->assertSame('0.00', $baseline->fresh()->score);
    }

    public function test_empty_and_invalid_baselines_cannot_activate(): void
    {
        [$plan, $baseline] = $this->fixture();
        $empty = DevelopmentPlan::factory()->create();
        $this->postJson("/api/plans/{$empty->id}/activate")->assertStatus(409);
        $baseline->update(['score' => 101]);
        $this->postJson("/api/plans/{$plan->id}/activate")->assertStatus(409);
        $this->assertNull($plan->fresh()->activated_at);
    }

    public function test_members_and_self_scoring_administrators_cannot_activate(): void
    {
        [$plan] = $this->fixture();
        Sanctum::actingAs($plan->member);
        $this->postJson("/api/plans/{$plan->id}/activate")->assertForbidden();
        $admin = User::factory()->administrator()->create();
        $plan->update(['user_id' => $admin->id]);
        Sanctum::actingAs($admin);
        $this->postJson("/api/plans/{$plan->id}/activate")->assertForbidden();
    }

    public function test_completion_requires_exact_set_and_closed_weeks(): void
    {
        [$plan, $baseline] = $this->fixture();
        $this->postJson("/api/plans/{$plan->id}/activate")->assertOk();
        $url = "/api/plans/{$plan->id}/complete";
        $this->postJson($url, ['finals' => [['skill_id' => Skill::factory()->create()->id, 'score' => 80]]])->assertStatus(409);
        $finals = [['skill_id' => $baseline->skill_id, 'score' => 80]];
        $week = WeeklyEntry::factory()->create(['development_plan_id' => $plan->id, 'skill_id' => $baseline->skill_id]);
        $this->postJson($url, ['finals' => $finals])->assertStatus(409);
        $this->assertSame(0, $plan->assessments()->where('type', 'final')->count());
        $week->update(['status' => 'closed', 'closed_at' => now(), 'evidence' => 'Verified', 'outcome_score' => 80]);
        $this->postJson($url, ['finals' => $finals])->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertNotNull($plan->fresh()->completed_at);
        $this->assertSame(1, $plan->baselineAssessments()->count());
        $this->assertSame(2, $plan->assessments()->count());
        $this->postJson($url, ['finals' => $finals])->assertStatus(409);
        $this->postJson("/api/plans/{$plan->id}/activate")->assertStatus(409);
        $this->patchJson("/api/plans/{$plan->id}/weeks/{$week->id}", ['objective' => 'Changed'])->assertStatus(409);
    }

    public function test_partial_duplicate_and_invalid_final_sets_are_rejected(): void
    {
        [$plan, $baseline] = $this->fixture();
        Assessment::factory()->create(['development_plan_id' => $plan->id]);
        $this->postJson("/api/plans/{$plan->id}/activate")->assertOk();
        $row = ['skill_id' => $baseline->skill_id, 'score' => 80];
        $this->postJson("/api/plans/{$plan->id}/complete", ['finals' => [$row]])->assertStatus(409);
        $this->postJson("/api/plans/{$plan->id}/complete", ['finals' => [$row, $row]])->assertUnprocessable();
        $row['score'] = 101;
        $this->postJson("/api/plans/{$plan->id}/complete", ['finals' => [$row]])->assertUnprocessable();
        $this->assertSame(0, $plan->assessments()->where('type', 'final')->count());
    }

    public function test_failure_during_final_insert_rolls_back_everything(): void
    {
        [$plan, $baseline] = $this->fixture();
        $other = Assessment::factory()->create(['development_plan_id' => $plan->id]);
        $this->postJson("/api/plans/{$plan->id}/activate")->assertOk();
        Assessment::creating(function (Assessment $row) use ($other) {
            if ($row->type->value === 'final' && $row->skill_id === $other->skill_id) {
                throw new \RuntimeException('Simulated second insert failure');
            }
        });
        try {
            $this->postJson("/api/plans/{$plan->id}/complete", ['finals' => [
                ['skill_id' => $baseline->skill_id, 'score' => 80],
                ['skill_id' => $other->skill_id, 'score' => 90],
            ]])->assertStatus(500);
            $this->assertSame(0, $plan->assessments()->where('type', 'final')->count());
            $this->assertSame('active', $plan->fresh()->status->value);
            $this->assertNull($plan->fresh()->completed_at);
        } finally {
            Assessment::flushEventListeners();
        }
    }

    public function test_individual_final_endpoint_is_blocked(): void
    {
        [$plan, $baseline] = $this->fixture();
        $this->postJson("/api/plans/{$plan->id}/assessments", [
            'skill_id' => $baseline->skill_id, 'type' => 'final', 'score' => 90,
        ])->assertStatus(409);
    }
}
