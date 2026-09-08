<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\DevelopmentPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AssessmentController extends Controller
{
    /**
     * Record a baseline or final score against a plan.
     *
     * Day 11 scope: the authorisation decision, which is where the integrity
     * rule lives — administrators only, and never on their own plan. The Policy
     * receives the plan because the decision depends on whose plan it is, not
     * on the score being written.
     *
     * Day 12 moves validation into a Form Request and shapes the response
     * through an API Resource. Day 13 adds the state-machine rules: a baseline
     * is immutable once the plan is active, a final requires a matching
     * baseline, and so on. Those are timing rules, not permission rules, and
     * they deliberately do not live here.
     */
    public function store(Request $request, DevelopmentPlan $plan): JsonResponse
    {
        Gate::authorize('create', [Assessment::class, $plan]);

        $validated = $request->validate([
            'skill_id' => ['required', 'integer', 'exists:skills,id'],
            'type' => ['required', 'in:baseline,final'],
            'score' => ['required', 'numeric', 'min:0', 'max:100'],
            'note' => ['nullable', 'string'],
        ]);

        $assessment = $plan->assessments()->create($validated + [
            'recorded_by' => $request->user()->id,
            'recorded_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'id' => $assessment->id,
                'development_plan_id' => $assessment->development_plan_id,
                'skill_id' => $assessment->skill_id,
                'type' => $assessment->type->value,
                'score' => $assessment->score,
            ],
        ], 201);
    }
}
