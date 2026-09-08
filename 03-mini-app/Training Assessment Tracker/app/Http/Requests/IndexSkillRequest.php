<?php

namespace App\Http\Requests;

use App\Models\Skill;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class IndexSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', Skill::class);
    }

    /**
     * A query string is input, and it is validated like input.
     *
     * `?active=maybe` must be a 422 naming the parameter, not a silently
     * ignored filter returning the wrong rows — a filter that fails quietly is
     * worse than one that fails loudly, because the caller believes the answer.
     *
     * `per_page` is bounded. Unbounded, one request asking for 100000 rows
     * loads the whole table into memory and hands pagination back to nobody.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'active' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }

    /**
     * HTTP has no booleans, and Laravel's `boolean` rule knows it.
     *
     * The rule accepts true, false, 1, 0, "1" and "0" — and rejects the strings
     * "true" and "false", because it was written for form posts where a browser
     * sends 1 or 0. A query string is not a form post: `?active=true` is the
     * spelling every HTTP client, every fetch() call and every curl line
     * actually produces, and unnormalised it was a 422 on a request that is
     * perfectly well formed.
     *
     * Normalising here rather than loosening the rule keeps the rejection
     * intact where it belongs: `?active=maybe` becomes null and still fails
     * `boolean`, so a nonsense filter is still a 422 naming the parameter.
     *
     * The JSON bodies elsewhere in this API need none of this — `{"is_active":
     * false}` carries a real boolean, and the rule accepts it unchanged.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('active')) {
            return;
        }

        $raw = $this->query('active');

        if (is_string($raw) && $raw !== '') {
            $this->merge([
                'active' => filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }
}
