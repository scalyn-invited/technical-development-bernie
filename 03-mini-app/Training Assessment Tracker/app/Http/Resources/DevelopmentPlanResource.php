<?php

namespace App\Http\Resources;

use App\Models\DevelopmentPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DevelopmentPlan
 */
class DevelopmentPlanResource extends JsonResource
{
    /**
     * A development plan.
     *
     * One Resource serves both the list and the read. The difference between
     * them is what the controller loaded, not what the class emits: the list
     * loads the member and two counts, the read loads the assessments and weeks.
     * Every relation is behind whenLoaded()/whenCounted(), so a key that is
     * absent means "not requested" and never "empty" — and no response can
     * trigger a lazy load on its way out of the controller.
     *
     * The member is reduced to id and name. The plan list is a management
     * screen, and it has no need of anyone's email address to render a row.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'member' => $this->whenLoaded('member', fn () => [
                'id' => $this->member->id,
                'name' => $this->member->name,
            ]),
            'key_gaps' => $this->key_gaps,
            'weekly_focus' => $this->weekly_focus,
            'status' => $this->status->value,
            'activated_at' => $this->activated_at,
            'completed_at' => $this->completed_at,
            'created_by' => $this->created_by,

            'assessments_count' => $this->whenCounted('assessments'),
            'weekly_entries_count' => $this->whenCounted('weeklyEntries'),
            'skills_count' => $this->whenHas('skills_count'),
            'current_week' => $this->whenHas('current_week'),

            'assessments' => AssessmentResource::collection($this->whenLoaded('assessments')),
            'weekly_entries' => WeeklyEntryResource::collection($this->whenLoaded('weeklyEntries')),
        ];
    }
}
