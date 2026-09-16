<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\DevelopmentPlan;
use App\Models\User;
use App\Models\WeeklyEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Day16ScreensTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_requires_auth_and_scopes_members_to_their_own_plans(): void
    {
        $this->getJson('/api/weeks/open')->assertUnauthorized();
        $member = User::factory()->create();
        $plan = DevelopmentPlan::factory()->active()->create(['user_id' => $member->id]);
        $own = WeeklyEntry::factory()->create(['development_plan_id' => $plan->id]);
        WeeklyEntry::factory()->create();
        WeeklyEntry::factory()->closed()->create(['development_plan_id' => $plan->id, 'week_number' => 2]);
        Sanctum::actingAs($member);
        $this->getJson('/api/weeks/open')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)->assertJsonPath('data.0.plan.member.id', $member->id);
    }

    public function test_admin_queue_filters_paginates_and_excludes_closed_weeks(): void
    {
        Sanctum::actingAs(User::factory()->administrator()->create());
        WeeklyEntry::factory()->count(2)->evidenced()->create();
        WeeklyEntry::factory()->create();
        WeeklyEntry::factory()->closed()->create();
        $this->getJson('/api/weeks/open?status=evidenced&per_page=1&page=2')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.current_page', 2)->assertJsonPath('data.0.status', 'evidenced');
        $this->getJson('/api/weeks/open?status=closed')->assertUnprocessable()->assertJsonValidationErrors('status', 'details');
        $this->getJson('/api/weeks/open?page=0')->assertUnprocessable();
    }

    public function test_plan_rows_count_baseline_skills_not_finals_and_report_first_open_week(): void
    {
        Sanctum::actingAs(User::factory()->administrator()->create());
        $plan = DevelopmentPlan::factory()->active()->create();
        $baseline = Assessment::factory()->create(['development_plan_id' => $plan->id]);
        Assessment::factory()->final()->create(['development_plan_id' => $plan->id, 'skill_id' => $baseline->skill_id]);
        WeeklyEntry::factory()->closed()->create(['development_plan_id' => $plan->id]);
        WeeklyEntry::factory()->create(['development_plan_id' => $plan->id, 'week_number' => 2]);
        $this->getJson('/api/plans?status=active')->assertOk()->assertJsonPath('data.0.skills_count', 1)
            ->assertJsonPath('data.0.current_week', 2)->assertJsonPath('data.0.weekly_entries_count', 2);
    }

    public function test_day16_direct_entry_routes_serve_the_vue_shell(): void
    {
        $this->withoutVite();
        foreach (['/plans', '/plans/1', '/open-weeks'] as $path) {
            $this->get($path)->assertOk()->assertSee('id="app"', false);
        }
    }
}
