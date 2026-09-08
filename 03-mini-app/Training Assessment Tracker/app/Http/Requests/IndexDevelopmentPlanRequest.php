<?php

namespace App\Http\Requests;

use App\Enums\PlanStatus;
use App\Models\DevelopmentPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class IndexDevelopmentPlanRequest extends FormRequest
{
    /**
     * Both roles may list plans. What each role *sees* is narrowed in the query,
     * not here — returning 403 to "show me what I am allowed to see" is the
     * wrong answer, and an empty or single-row list is the right one.
     */
    public function authorize(): bool
    {
        return Gate::allows('viewAny', DevelopmentPlan::class);
    }

    /**
     * `Rule::enum` validates the filter against the same backed enum the column
     * casts to, so the set of valid filters cannot drift from the set of valid
     * statuses. `?status=archived` is 422.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(PlanStatus::class)],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
