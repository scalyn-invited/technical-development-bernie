<?php

namespace App\Http\Requests;

use App\Enums\WeeklyEntryStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateWeeklyEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('weekly_entry'));
    }

    public function rules(): array
    {
        // Only submitted fields: the service merges the freshly locked row,
        // then checks required evidence/outcome for the resulting status.
        return [
            'status' => ['sometimes', Rule::enum(WeeklyEntryStatus::class)],
            'objective' => ['sometimes', 'required', 'string', 'max:2000'],
            'evidence' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'outcome_score' => ['sometimes', 'nullable', 'numeric', 'between:0,100'],
            'skill_id' => ['prohibited'],
            'week_number' => ['prohibited'],
        ];
    }
}
