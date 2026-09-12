<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAssessmentRequest;
use App\Http\Resources\AssessmentResource;
use App\Models\Assessment;
use App\Models\DevelopmentPlan;
use App\Services\ProgrammeProgressionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssessmentController extends Controller
{
    public function store(StoreAssessmentRequest $request, DevelopmentPlan $plan, ProgrammeProgressionService $service): JsonResponse
    {
        $assessment = $service->recordBaseline($plan, $request->user(), $request->validated());

        return (new AssessmentResource($assessment->load('skill')))->response()->setStatusCode(201);
    }

    public function update(Request $request, DevelopmentPlan $plan, Assessment $assessment, ProgrammeProgressionService $service): AssessmentResource
    {
        $assessment = $service->correctBaseline($plan, $assessment, $request->user(), $request->all());

        return new AssessmentResource($assessment->load('skill'));
    }
}
