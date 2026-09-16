<?php

namespace App\Http\Resources;

use App\Models\WeeklyEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WeeklyEntry
 */
class WeeklyEntryResource extends JsonResource
{
    /**
     * One week of the plan.
     *
     * `closed_at` is null for every status but `closed`, and the controller sets
     * it as part of the same write that sets the status — a closed week reporting
     * a null timestamp would be the Resource stating something untrue about the
     * row it is describing.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'development_plan_id' => $this->development_plan_id,
            'week_number' => $this->week_number,
            'skill_id' => $this->skill_id,
            'skill' => new SkillResource($this->whenLoaded('skill')),
            'objective' => $this->objective,
            'evidence' => $this->evidence,
            'outcome_score' => $this->outcome_score,
            'status' => $this->status->value,
            'recorded_by' => $this->recorded_by,
            'closed_at' => $this->closed_at,
            'plan' => $this->whenLoaded('developmentPlan', fn () => [
                'id' => $this->developmentPlan->id,
                'status' => $this->developmentPlan->status->value,
                'member' => [
                    'id' => $this->developmentPlan->member->id,
                    'name' => $this->developmentPlan->member->name,
                ],
            ]),
        ];
    }
}
