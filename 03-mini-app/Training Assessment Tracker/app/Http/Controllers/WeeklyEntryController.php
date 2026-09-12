<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWeeklyEntryRequest;
use App\Http\Requests\UpdateWeeklyEntryRequest;
use App\Http\Resources\WeeklyEntryResource;
use App\Models\DevelopmentPlan;
use App\Models\WeeklyEntry;
use App\Services\ProgrammeProgressionService;
use Illuminate\Http\JsonResponse;

class WeeklyEntryController extends Controller
{
    public function store(StoreWeeklyEntryRequest $request, DevelopmentPlan $plan, ProgrammeProgressionService $service): JsonResponse
    {
        $entry = $service->createWeek($plan, $request->user(), $request->validated());

        return (new WeeklyEntryResource($entry->load('skill')))->response()->setStatusCode(201);
    }

    public function update(UpdateWeeklyEntryRequest $request, DevelopmentPlan $plan, WeeklyEntry $weeklyEntry, ProgrammeProgressionService $service): WeeklyEntryResource
    {
        $entry = $service->updateWeek($plan, $weeklyEntry, $request->user(), $request->validated());

        return new WeeklyEntryResource($entry->load('skill'));
    }
}
