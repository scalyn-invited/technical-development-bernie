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

class RemainingProgressionTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $admin = User::factory()->administrator()->create();
        Sanctum::actingAs($admin);
        $plan = DevelopmentPlan::factory()->create();
        $baseline = Assessment::factory()->create(['development_plan_id' => $plan->id, 'score' => '80.25']);

        return [$plan, $baseline, $admin];
    }

    public function test_full_cycle_and_attack_sequences(): void
    {
        [$plan, $baseline] = $this->fixture();
        $root = "/api/plans/{$plan->id}";
        $this->patchJson("$root/assessments/{$baseline->id}", ['score' => 81.25])->assertOk();
        $this->postJson("$root/activate")->assertOk();
        $this->patchJson("$root/assessments/{$baseline->id}", ['score' => 1])->assertStatus(409);
        $week = ['week_number' => 1, 'skill_id' => $baseline->skill_id, 'objective' => 'Prove the rules'];
        $this->postJson("$root/weeks", array_replace($week, ['week_number' => 2]))->assertStatus(409);
        $id = $this->postJson("$root/weeks", $week)->assertCreated()->json('data.id');
        $url = "$root/weeks/$id";
        $this->postJson("$root/weeks", array_replace($week, ['week_number' => 2]))->assertStatus(409);
        $this->patchJson($url, ['status' => 'closed', 'evidence' => 'Proof', 'outcome_score' => 0])->assertStatus(409);
        $this->patchJson($url, ['status' => 'evidenced'])->assertUnprocessable();
        $this->patchJson($url, ['status' => 'evidenced', 'evidence' => 'Proof', 'outcome_score' => 0])->assertOk();
        $this->patchJson($url, ['status' => 'planned'])->assertStatus(409);
        $this->patchJson($url, ['status' => 'closed', 'evidence' => null])->assertUnprocessable();
        $this->patchJson("/api/skills/{$baseline->skill_id}", ['is_active' => false])->assertStatus(409);
        $this->patchJson($url, ['status' => 'closed'])->assertOk();
        $closedAt = WeeklyEntry::findOrFail($id)->closed_at->toISOString();
        $this->patchJson($url, ['evidence' => 'Overwrite'])->assertStatus(409);
        $this->assertSame($closedAt, WeeklyEntry::findOrFail($id)->closed_at->toISOString());
        $this->patchJson("/api/skills/{$baseline->skill_id}", ['is_active' => false])->assertOk();
        $this->postJson("$root/weeks", array_replace($week, ['week_number' => 2]))->assertStatus(409);
        $this->postJson("$root/complete", ['finals' => [['skill_id' => $baseline->skill_id, 'score' => 80.10]]])->assertOk();
        $this->getJson("$root/comparison")->assertOk()
            ->assertJsonPath('data.0.baseline_score', '81.25')
            ->assertJsonPath('data.0.final_score', '80.10')
            ->assertJsonPath('data.0.delta', '-1.15')
            ->assertJsonPath('data.0.is_active', false);
        $this->patchJson("$root/assessments/{$baseline->id}", ['score' => 99])->assertStatus(409);
        $final = $plan->assessments()->where('type', 'final')->firstOrFail();
        $this->patchJson("$root/assessments/{$final->id}", ['score' => 99])->assertStatus(409);
        $this->postJson("$root/weeks", array_replace($week, ['week_number' => 2]))->assertStatus(409);
    }

    public function test_focus_and_next_week_rules(): void
    {
        [$plan, $baseline] = $this->fixture();
        $url = "/api/plans/{$plan->id}/weeks";
        $data = ['week_number' => 1, 'skill_id' => $baseline->skill_id, 'objective' => 'Practice'];
        $this->postJson($url, $data)->assertStatus(409);
        $this->postJson("/api/plans/{$plan->id}/activate")->assertOk();
        $this->postJson($url, array_replace($data, ['skill_id' => Skill::factory()->create()->id]))->assertStatus(409);
        $id = $this->postJson($url, $data)->assertCreated()->json('data.id');
        $this->patchJson("$url/$id", ['skill_id' => 1])->assertUnprocessable();
        $this->patchJson("$url/$id", ['status' => 'evidenced', 'evidence' => 'Done'])->assertOk();
        $this->patchJson("$url/$id", ['status' => 'closed'])->assertUnprocessable();
        $this->patchJson("$url/$id", ['status' => 'closed', 'outcome_score' => 100])->assertOk();
        $this->postJson($url, array_replace($data, ['week_number' => 3]))->assertStatus(409);
        $this->postJson($url, array_replace($data, ['week_number' => 2]))->assertCreated();
    }

    public function test_corrections_and_comparisons_are_scoped_and_authorized(): void
    {
        [$plan, $baseline, $admin] = $this->fixture();
        $other = Assessment::factory()->create();
        $url = "/api/plans/{$plan->id}/assessments/{$baseline->id}";
        $this->patchJson("/api/plans/{$plan->id}/assessments/{$other->id}", ['score' => 50])->assertNotFound();
        $this->patchJson($url, ['score' => -1])->assertUnprocessable();
        $this->patchJson($url, ['type' => 'final'])->assertUnprocessable();
        $this->patchJson($url, ['score' => 0, 'note' => null])->assertOk()->assertJsonPath('data.score', '0.00');
        Sanctum::actingAs($plan->member);
        $this->patchJson($url, ['score' => 50])->assertForbidden();
        $this->getJson("/api/plans/{$plan->id}/comparison")->assertOk()->assertJsonPath('data.0.delta', null);
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/plans/{$plan->id}/comparison")->assertForbidden();
        $plan->update(['user_id' => $admin->id]);
        Sanctum::actingAs($admin);
        $this->patchJson($url, ['score' => 50])->assertForbidden();
    }

    public function test_deactivation_checks_open_weeks_on_other_plans(): void
    {
        [$plan, $baseline] = $this->fixture();
        $otherWeek = WeeklyEntry::factory()->create(['skill_id' => $baseline->skill_id]);
        $url = "/api/skills/{$baseline->skill_id}";
        $this->patchJson($url, ['is_active' => false])->assertStatus(409);
        $this->patchJson($url, ['name' => 'Renamed'])->assertOk();
        $this->assertTrue($baseline->skill->fresh()->is_active);
        $otherWeek->update(['status' => 'closed']);
        $this->patchJson($url, ['is_active' => false])->assertOk();
        $this->getJson("/api/plans/{$plan->id}")->assertOk()->assertJsonPath('data.assessments.0.skill.id', $baseline->skill_id);
    }

    public function test_comparison_handles_zero_positive_and_orphan_final(): void
    {
        [$plan, $baseline] = $this->fixture();
        $final = Assessment::factory()->final()->create([
            'development_plan_id' => $plan->id, 'skill_id' => $baseline->skill_id, 'score' => '80.25',
        ]);
        $url = "/api/plans/{$plan->id}/comparison";
        $this->getJson($url)->assertOk()->assertJsonPath('data.0.delta', '0.00');
        $final->update(['score' => '80.26']);
        $this->getJson($url)->assertOk()->assertJsonPath('data.0.delta', '0.01');
        Assessment::factory()->final()->create(['development_plan_id' => $plan->id]);
        $this->getJson($url)->assertStatus(409);
    }

    public function test_new_baseline_cannot_select_retired_skill(): void
    {
        [$plan] = $this->fixture();
        $skill = Skill::factory()->create(['is_active' => false]);
        $this->postJson("/api/plans/{$plan->id}/assessments", [
            'type' => 'baseline', 'skill_id' => $skill->id, 'score' => 50,
        ])->assertStatus(409);
        $this->assertDatabaseMissing('assessments', [
            'development_plan_id' => $plan->id, 'skill_id' => $skill->id,
        ]);
    }
}
