<?php

namespace App\Http\Controllers;

use App\Http\Resources\DevelopmentPlanResource;
use App\Models\DevelopmentPlan;
use App\Services\ProgrammeProgressionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PlanProgressionController extends Controller
{
    public function comparison(Request $request, DevelopmentPlan $plan, ProgrammeProgressionService $service): JsonResponse
    {
        return response()->json($service->comparison($plan, $request->user()));
    }

    public function activate(Request $request, DevelopmentPlan $plan, ProgrammeProgressionService $service): DevelopmentPlanResource
    {
        return new DevelopmentPlanResource($service->activate($plan, $request->user()));
    }

    public function complete(Request $request, DevelopmentPlan $plan, ProgrammeProgressionService $service): DevelopmentPlanResource
    {
        Gate::authorize('update', $plan);
        $data = $request->validate(['finals' => ['required', 'array', 'min:1']]);

        return new DevelopmentPlanResource($service->complete($plan, $request->user(), $data['finals']));
    }
}
