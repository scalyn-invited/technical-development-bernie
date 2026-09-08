<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('skill'));
    }

    /**
     * Rename or deactivate — the only two things that happen to a skill.
     *
     * The unique rule ignores the current record, without which renaming a
     * skill to the name it already has would be rejected as a duplicate of
     * itself. `sometimes` throughout, because PATCH means "change these fields"
     * and an absent field must not be read as an instruction to blank it.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('skills', 'name')->ignore($this->route('skill')),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
