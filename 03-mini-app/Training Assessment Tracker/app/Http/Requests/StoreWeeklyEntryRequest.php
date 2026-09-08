<?php

namespace App\Http\Requests;

use App\Models\WeeklyEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreWeeklyEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', [WeeklyEntry::class, $this->route('plan')]);
    }

    /**
     * A week is always created `planned`, and `status` is not accepted.
     *
     * Accepting it would let a caller POST a week that is already `closed`,
     * skipping the transition and every rule attached to it — the same bug
     * class as Day 11's register() accepting a `role`: one payload field that
     * routes around the model the rest of the application is built on.
     *
     * `between:1,255` matches the unsignedTinyInteger column exactly. Left to
     * `integer` alone, week 300 passes validation and the database is asked to
     * store a value the column cannot hold.
     *
     * Week *contiguity* — week N refused until N−1 is closed — is Day 13's.
     * This rule only prevents two rows claiming the same week.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'week_number' => [
                'required', 'integer', 'between:1,255',
                Rule::unique('weekly_entries', 'week_number')
                    ->where('development_plan_id', $this->route('plan')?->id),
            ],
            'skill_id' => ['required', 'integer', 'exists:skills,id'],
            'objective' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'week_number.unique' => 'Week :input is already logged on this plan.',
        ];
    }
}
