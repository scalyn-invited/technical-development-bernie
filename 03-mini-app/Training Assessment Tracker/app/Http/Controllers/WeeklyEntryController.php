<?php

namespace App\Http\Controllers;

use App\Enums\WeeklyEntryStatus;
use App\Http\Requests\StoreWeeklyEntryRequest;
use App\Http\Requests\UpdateWeeklyEntryRequest;
use App\Http\Resources\WeeklyEntryResource;
use App\Models\DevelopmentPlan;
use App\Models\WeeklyEntry;
use Illuminate\Http\JsonResponse;

class WeeklyEntryController extends Controller
{
    /**
     * Log a week's objective.
     *
     * Always created `planned` — the Form Request does not accept `status`, and
     * the default is restated here rather than left to the column so the
     * created row and the response cannot disagree about it.
     */
    public function store(StoreWeeklyEntryRequest $request, DevelopmentPlan $plan): JsonResponse
    {
        $entry = $plan->weeklyEntries()->create($request->validated() + [
            'status' => WeeklyEntryStatus::Planned,
            'recorded_by' => $request->user()->id,
        ]);

        $entry->load('skill');

        return (new WeeklyEntryResource($entry))->response()->setStatusCode(201);
    }

    /**
     * Record evidence, an outcome, or close the week.
     *
     * `closed_at` is derived from the status in the same write that sets it,
     * never accepted from the client and never left behind. A week reported as
     * closed with a null timestamp would be the API stating something untrue
     * about its own row, and the first person to notice would be the reviewer
     * on Day 18.
     *
     * The already-closed case keeps its original timestamp: re-closing a week
     * must not silently reset when it was closed.
     *
     * The plan is bound and passed in so the route can be scoped — see
     * routes/api.php. It is not used in the body; the entry carries everything
     * the write needs.
     *
     * Day 13 owns the transitions themselves: planned → evidenced → closed and
     * nothing else, and a closed week is read-only. Today, status may still be
     * set in any direction.
     */
    public function update(
        UpdateWeeklyEntryRequest $request,
        DevelopmentPlan $plan,
        WeeklyEntry $weeklyEntry
    ): WeeklyEntryResource {
        $data = $request->validated();

        if (array_key_exists('status', $data)) {
            $data['closed_at'] = WeeklyEntryStatus::from($data['status']) === WeeklyEntryStatus::Closed
                ? ($weeklyEntry->closed_at ?? now())
                : null;
        }

        $weeklyEntry->update($data);

        $weeklyEntry->load('skill');

        return new WeeklyEntryResource($weeklyEntry);
    }
}
