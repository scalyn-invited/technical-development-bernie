<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePlayerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules() {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:players,email,' . $this->player?->id,
            'team_id' => 'required|exists:teams,id',
            'jersey_number' => 'unique:players,jersey_number,' . $this->player?->id,
            'phone' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (strlen($value) < 10) {
                        $fail('Phone must be at least 10 digits.');
                    }
                },
            ],
        ];
    }
}
