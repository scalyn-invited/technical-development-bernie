<?php

namespace App\Http\Requests;

use App\Models\Skill;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Skill::class);
    }

    /**
     * `is_active` is not accepted here. A new skill is active; retiring one is
     * PATCH's job, and it is the deliberate act the whole "deactivate, never
     * delete" rule is built around. Creating a skill pre-retired is not a
     * workflow the tool has.
     *
     * `created_by` is not accepted either — it is taken from the token. Any
     * field the server can determine for itself is a field the client should
     * not be trusted to state.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:skills,name'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
