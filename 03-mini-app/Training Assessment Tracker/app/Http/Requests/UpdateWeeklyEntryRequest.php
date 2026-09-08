<?php

namespace App\Http\Requests;

use App\Enums\WeeklyEntryStatus;
use App\Models\WeeklyEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateWeeklyEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('weekly_entry'));
    }

    /**
     * The day's conditional rule, stated as prescribed: a week may be saved as
     * a draft objective, but may not be closed empty.
     *
     * `required_if` is an implicit rule, so it still fires on a field marked
     * `nullable` — sending "evidence": null with "status": "closed" is refused,
     * which is the case that matters and the one a plain `nullable` would let
     * through.
     *
     * Whether the transition itself is legal — planned → evidenced → closed,
     * and a closed week never reopening — is Day 13's. This request only
     * refuses a close that would leave the row without the two fields the
     * close is supposed to record.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(WeeklyEntryStatus::class)],
            'objective' => ['sometimes', 'required', 'string', 'max:2000'],
            'evidence' => ['nullable', 'string', 'max:2000', 'required_if:status,closed'],
            'outcome_score' => ['nullable', 'numeric', 'between:0,100', 'required_if:status,closed'],
        ];
    }

    /**
     * Close the gap between what `required_if` checks and what the rule means.
     *
     * `required_if:status,closed` inspects the *payload*. The rule's intent is
     * that a *week* may not be closed empty. Those are the same thing only when
     * the caller closes a week in a single request. The natural two-step —
     * PATCH the evidence and the outcome, then PATCH {"status":"closed"} — is
     * refused 422 by the literal rule, even though the row being closed already
     * holds both fields. That is the rule rejecting a correct request.
     *
     * Merging the persisted values in first means `required_if` is applied to
     * the state the row will actually be in after the write, which is the state
     * the rule is about. A caller who has recorded neither still gets the 422
     * the rule exists to produce.
     */
    protected function prepareForValidation(): void
    {
        $entry = $this->route('weekly_entry');

        if (! $entry instanceof WeeklyEntry) {
            return;
        }

        $carried = [];

        if (! $this->has('evidence') && $entry->evidence !== null) {
            $carried['evidence'] = $entry->evidence;
        }

        if (! $this->has('outcome_score') && $entry->outcome_score !== null) {
            $carried['outcome_score'] = $entry->outcome_score;
        }

        $this->merge($carried);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'evidence.required_if' => 'A week cannot be closed without evidence.',
            'outcome_score.required_if' => 'A week cannot be closed without an outcome score.',
        ];
    }
}
