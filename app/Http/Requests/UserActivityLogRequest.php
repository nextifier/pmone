<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserActivityLogRequest extends FormRequest
{
    /**
     * Authorisation is handled by the route's can: middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `per_page` deliberately has no max rule: the controller clamps it, so an
     * oversized page size degrades to the cap instead of failing the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1'],
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'search.max' => 'Search terms are limited to 120 characters.',
        ];
    }
}
