<?php

namespace Tests\Feature;

use App\Exceptions\ProgressionConflict;
use App\Models\DevelopmentPlan;
use App\Models\Skill;
use App\Models\User;
use App\Services\ProgrammeProgressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FullCycleTest extends TestCase
{
    use RefreshDatabase;

    private function payload(User $member, Skill $skill): array
    {
        return ['user_id' => $member->id, 'key_gaps' => 'API design', 'weekly_focus' => 'Build and verify',
            'baselines' => [['skill_id' => $skill->id, 'score' => 0, 'note' => 'Starting point']]];
    }

    public function test_fresh_member_to_completed_comparison_through_http(): void
    {
        $memberId = $this->postJson('/api/register', ['name' => 'Cycle Member', 'email' => 'cycle@example.test',
            'password' => 'test-password', 'password_confirmation' => 'test-password', 'role' => 'administrator'])
            ->assertCreated()->assertJsonPath('data.role', 'member')->json('data.id');
        $member = User::findOrFail($memberId);
        $admin = User::factory()->administrator()->create();
        $skill = Skill::factory()->create();
        Sanctum::actingAs($admin);
        $this->getJson('/api/members/eligible')->assertOk()->assertJsonPath('data.0.id', $member->id);
        $id = $this->postJson('/api/plans', $this->payload($member, $skill))->assertCreated()
            ->assertJsonPath('data.status', 'draft')->assertJsonPath('data.created_by', $admin->id)->json('data.id');
        $assessment = DevelopmentPlan::findOrFail($id)->assessments()->first();
        $this->patchJson("/api/plans/$id/assessments/$assessment->id", ['score' => 20])->assertOk();
        $this->postJson("/api/plans/$id/activate")->assertOk();
        $week = $this->postJson("/api/plans/$id/weeks", ['week_number' => 1, 'skill_id' => $skill->id, 'objective' => 'Build API'])
            ->assertCreated()->json('data.id');
        $this->patchJson("/api/plans/$id/weeks/$week", ['status' => 'evidenced', 'evidence' => 'Tests passed'])->assertOk();
        $this->patchJson("/api/plans/$id/weeks/$week", ['status' => 'closed', 'outcome_score' => 0])->assertOk();
        $this->postJson("/api/plans/$id/complete", ['finals' => [['skill_id' => $skill->id, 'score' => 80]]])
            ->assertOk()->assertJsonPath('data.status', 'completed');
        $this->postJson("/api/plans/$id/complete", ['finals' => [['skill_id' => $skill->id, 'score' => 90]]])->assertConflict();
        Sanctum::actingAs($member);
        $this->getJson("/api/plans/$id/comparison")->assertOk()->assertJsonPath('data.0.delta', '60.00')
            ->assertJsonPath('summary.average_movement', '60.00');
        $this->getJson('/api/plans')->assertJsonCount(1, 'data');
        $this->patchJson("/api/plans/$id/weeks/$week", ['evidence' => 'Overwrite'])->assertForbidden();
    }

    public function test_creation_and_member_directory_are_admin_only(): void
    {
        $this->getJson('/api/members/eligible')->assertUnauthorized();
        $this->postJson('/api/plans', [])->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/members/eligible')->assertForbidden();
        $this->postJson('/api/plans', [])->assertForbidden();
    }

    public function test_invalid_or_duplicate_creation_never_leaves_partial_plan(): void
    {
        Sanctum::actingAs($admin = User::factory()->administrator()->create());
        $member = User::factory()->create();
        $skill = Skill::factory()->create();
        $payload = $this->payload($member, $skill);
        $bad = $payload;
        $bad['baselines'][] = ['skill_id' => Skill::factory()->create()->id, 'score' => 101];
        $this->postJson('/api/plans', $bad)->assertUnprocessable()->assertJsonValidationErrors('baselines.1.score', 'details');
        $this->assertDatabaseCount('development_plans', 0);
        $this->assertDatabaseCount('assessments', 0);
        $this->postJson('/api/plans', $payload + ['status' => 'completed'])->assertUnprocessable();
        $this->postJson('/api/plans', array_replace($payload, ['user_id' => $admin->id]))->assertUnprocessable();
        $this->postJson('/api/plans', $payload)->assertCreated();
        $this->postJson('/api/plans', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('development_plans', 1);
        $this->assertDatabaseCount('assessments', 1);
        $this->getJson('/api/members/eligible')->assertJsonCount(0, 'data');
    }

    public function test_service_failure_rolls_back_plan_and_earlier_baselines(): void
    {
        $admin = User::factory()->administrator()->create();
        $member = User::factory()->create();
        $skill = Skill::factory()->create();
        $retired = Skill::factory()->create(['is_active' => false]);
        $payload = $this->payload($member, $skill);
        $payload['baselines'][] = ['skill_id' => $retired->id, 'score' => 20];
        try {
            app(ProgrammeProgressionService::class)->createPlan($admin, $payload);
            $this->fail('Expected inactive skill conflict.');
        } catch (ProgressionConflict $e) {
            $this->assertDatabaseCount('development_plans', 0);
            $this->assertDatabaseCount('assessments', 0);
        }
    }

    public function test_eligible_members_pagination_search_and_minimal_fields(): void
    {
        Sanctum::actingAs(User::factory()->administrator()->create());
        User::factory()->count(21)->create(['name' => 'Eligible']);
        $this->getJson('/api/members/eligible?search=Eligible&page=2')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('total', 21)->assertJsonMissingPath('data.0.email');
        $this->getJson('/api/members/eligible?page=0')->assertUnprocessable();
        $this->withoutVite()->get('/plans/new')->assertOk()->assertSee('id="app"', false);
        $this->get('/register')->assertOk()->assertSee('id="app"', false);
    }
}
