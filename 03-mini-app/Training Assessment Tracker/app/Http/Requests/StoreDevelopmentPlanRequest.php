<?php

namespace App\Http\Requests;

use App\Models\DevelopmentPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreDevelopmentPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', DevelopmentPlan::class);
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'member'), 'unique:development_plans,user_id'],
            'key_gaps' => ['required', 'string', 'max:2000'],
            'weekly_focus' => ['required', 'string', 'max:2000'],
            'baselines' => ['required', 'array', 'min:1'],
            'baselines.*.skill_id' => ['required', 'integer', 'distinct', Rule::exists('skills', 'id')->where('is_active', true)],
            'baselines.*.score' => ['required', 'numeric', 'between:0,100'],
            'baselines.*.note' => ['nullable', 'string', 'max:2000'],
            'status' => ['prohibited'],
            'created_by' => ['prohibited'],
        ];
    }
}
