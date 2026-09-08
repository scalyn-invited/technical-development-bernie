<?php

namespace App\Http\Controllers;

use App\Enums\PlanStatus;
use App\Http\Requests\IndexDevelopmentPlanRequest;
use App\Http\Resources\DevelopmentPlanResource;
use App\Models\DevelopmentPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PlanController extends Controller
{
    /**
     * List plans.
     *
     * This is the narrowing the Day 11 notes promised would live in the query
     * rather than the Policy: `viewAny` allows both roles, and a member's list
     * is filtered to their own plan here. A member asking for the list gets 200
     * and one row, not a 403 — a 403 is the wrong answer to "show me what I am
     * allowed to see", and it also tells the caller that a list exists which
     * they cannot have.
     *
     * withCount() rather than with() for the two counts: the list renders a
     * skill count and a week count, and loading every assessment row to call
     * count() on it is the N+1 shaped like a feature. Fixed cost regardless of
     * page size.
     */
    public function index(IndexDevelopmentPlanRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $plans = DevelopmentPlan::query()
            ->with('member')
            ->withCount(['assessments', 'weeklyEntries'])
            ->when(
                ! $user->isAdministrator(),
                fn ($query) => $query->where('user_id', $user->id)
            )
            ->when(
                $request->filled('status'),
                fn ($query) => $query->status(PlanStatus::from($request->string('status')->toString()))
            )
            ->orderBy('id')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return DevelopmentPlanResource::collection($plans);
    }

    /**
     * One plan with its assessments and weeks.
     *
     * No Form Request, because there is no input to validate — so the Gate call
     * stays in the controller. Everything the Resource can emit is loaded here
     * in four queries, including the skill on each nested row: the Resource
     * guards each relation with whenLoaded(), so a relation missed here comes
     * back as an absent key rather than as a lazy load repeated per row.
     */
    public function show(Request $request, DevelopmentPlan $plan): DevelopmentPlanResource
    {
        Gate::authorize('view', $plan);

        $plan->load([
            'member',
            'assessments' => fn ($query) => $query->orderBy('skill_id')->orderBy('type'),
            'assessments.skill',
            'weeklyEntries' => fn ($query) => $query->orderBy('week_number'),
            'weeklyEntries.skill',
        ]);

        return new DevelopmentPlanResource($plan);
    }
}
