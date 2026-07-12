<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreApiConsumerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasAnyRole(['master', 'admin']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'website_url' => ['required', 'url', 'max:500'],
            'description' => ['nullable', 'string', 'max:1000'],
            'allowed_origins' => ['nullable', 'array'],
            'allowed_origins.*' => ['url'],
            'rate_limit' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['integer', 'exists:projects,id'],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The consumer name is required.',
            'name.max' => 'Name must not exceed 255 characters.',
            'website_url.required' => 'The website URL is required.',
            'website_url.url' => 'Website URL must be a valid URL.',
            'website_url.max' => 'Website URL must not exceed 500 characters.',
            'description.max' => 'Description must not exceed 1000 characters.',
            'allowed_origins.array' => 'Allowed origins must be an array.',
            'allowed_origins.*.url' => 'Each allowed origin must be a valid URL.',
            'rate_limit.integer' => 'Rate limit must be an integer.',
            'rate_limit.min' => 'Rate limit must be 0 (unlimited) or at least 10 requests per minute.',
            'rate_limit.max' => 'Rate limit must not exceed 10000 requests per minute.',
            'is_active.boolean' => 'Active status must be true or false.',
            'project_ids.array' => 'Project scope must be a list of project IDs.',
            'project_ids.*.exists' => 'One or more selected projects do not exist.',
        ];
    }
}
