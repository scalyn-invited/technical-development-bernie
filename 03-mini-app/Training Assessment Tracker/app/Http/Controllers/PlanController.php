<?php

namespace App\Http\Controllers;

use App\Models\DevelopmentPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PlanController extends Controller
{
    /**
     * Read one development plan.
     *
     * Day 11 scope: the authorisation decision only. Gate::authorize throws an
     * AuthorizationException, which the handler renders as the 403 envelope —
     * the controller never formats an error itself.
     *
     * Day 12 replaces the payload below with a DevelopmentPlanResource carrying
     * eagerly loaded assessments and weekly entries.
     */
    public function show(DevelopmentPlan $plan): JsonResponse
    {
        Gate::authorize('view', $plan);

        return response()->json([
            'data' => [
                'id' => $plan->id,
                'user_id' => $plan->user_id,
                'status' => $plan->status->value,
                'key_gaps' => $plan->key_gaps,
                'weekly_focus' => $plan->weekly_focus,
            ],
        ]);
    }
}
