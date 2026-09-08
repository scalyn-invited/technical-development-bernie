<?php

namespace App\Http\Resources;

use App\Models\Assessment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Assessment
 */
class AssessmentResource extends JsonResource
{
    /**
     * A recorded score.
     *
     * `score` is left exactly as the `decimal:2` cast produced it — a string.
     * Casting it to a float here to make the JSON "look right" would reintroduce
     * the rounding the cast exists to prevent, on the one figure the tool exists
     * to report.
     *
     * The nested skill is behind whenLoaded(), so this Resource can be used from
     * the plan read (where the relation is eager loaded) and from the create
     * response (where it is not) without either lazy-loading or lying.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'development_plan_id' => $this->development_plan_id,
            'skill_id' => $this->skill_id,
            'skill' => new SkillResource($this->whenLoaded('skill')),
            'type' => $this->type->value,
            'score' => $this->score,
            'note' => $this->note,
            'recorded_by' => $this->recorded_by,
            'recorded_at' => $this->recorded_at,
        ];
    }
}
