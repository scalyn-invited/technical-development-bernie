<?php

namespace App\Http\Requests;

use App\Enums\AssessmentType;
use App\Models\Assessment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreAssessmentRequest extends FormRequest
{
    /**
     * The integrity rule, reached before any rule below runs.
     *
     * Laravel resolves authorize() before rules(), which is the ordering this
     * endpoint needs: a member sending a malformed score must be told 403 and
     * not 422. A 422 would answer a question they are not entitled to ask —
     * it describes the shape of a payload they may never send.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', [Assessment::class, $this->route('plan')]);
    }

    /**
     * Shape only. Whether this score may be recorded *at this point in the
     * plan's life* — a baseline on an active plan, a final with no matching
     * baseline — is a state-machine question and belongs to Day 13's
     * ProgrammeProgressionService, not to validation.
     *
     * Two rules here are worth their comment:
     *
     * - `between:0,100` is the only place the score range exists. The column is
     *   decimal(5,2), which accepts 999.99 (TECHNICAL-NOTES.md §5.7), so a
     *   seeder or a direct write can still store a score this endpoint rejects.
     *
     * - The unique rule is the Day 10 composite constraint
     *   unique(development_plan_id, skill_id, type) restated where a caller can
     *   act on it. Without it the second identical baseline reaches the database
     *   and returns a 500 — a failure path outside the envelope, which the
     *   acceptance criteria for today do not allow.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'skill_id' => [
                'required', 'integer', 'exists:skills,id',
                Rule::unique('assessments', 'skill_id')
                    ->where('development_plan_id', $this->route('plan')?->id)
                    ->where('type', $this->input('type')),
            ],
            'type' => ['required', Rule::enum(AssessmentType::class)],
            'score' => ['required', 'numeric', 'between:0,100'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'skill_id.unique' => 'A score of this type is already recorded for this skill on this plan.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['skill_id' => 'skill'];
    }
}
