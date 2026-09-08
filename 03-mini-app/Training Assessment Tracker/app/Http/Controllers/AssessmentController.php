<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAssessmentRequest;
use App\Http\Resources\AssessmentResource;
use App\Models\DevelopmentPlan;
use Illuminate\Http\JsonResponse;

class AssessmentController extends Controller
{
    /**
     * Record a baseline or final score against a plan.
     *
     * The controller does three things and no more: write, load what the
     * response describes, return 201. Authorisation — including the integrity
     * rule, that nobody scores their own plan — is decided in
     * StoreAssessmentRequest::authorize() before validation runs, so a caller
     * who may not write here is never told what a valid payload would look
     * like.
     *
     * `recorded_by` and `recorded_at` come from the token and the clock, never
     * from the request. A score whose author the client can nominate is not
     * audit metadata.
     *
     * Day 13 puts the state-machine rules in front of this write: a baseline is
     * immutable once the plan is active, a final requires a matching baseline
     * and may be written once, and the final set is written inside the same
     * transaction that completes the plan. Today the endpoint will accept a
     * final with no baseline behind it. That is deliberate and is Day 13's job.
     */
    public function store(StoreAssessmentRequest $request, DevelopmentPlan $plan): JsonResponse
    {
        $assessment = $plan->assessments()->create($request->validated() + [
            'recorded_by' => $request->user()->id,
            'recorded_at' => now(),
        ]);

        $assessment->load('skill');

        return (new AssessmentResource($assessment))->response()->setStatusCode(201);
    }
}
