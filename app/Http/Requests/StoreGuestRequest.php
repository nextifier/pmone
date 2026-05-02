<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreGuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            'title' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:50000'],

            'organization' => ['nullable', 'string', 'max:255'],

            'status' => ['sometimes', 'string', 'in:active,inactive'],
            'visibility' => ['sometimes', 'string', 'in:public,private'],
            'is_featured' => ['sometimes', 'boolean'],

            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],

            'more_details' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],

            'links' => ['nullable', 'array', 'max:20'],
            'links.*.label' => ['required_with:links.*.url', 'string', 'max:100'],
            'links.*.url' => ['required_with:links.*.label', 'url:http,https', 'max:500'],

            'tmp_profile_image' => ['nullable', 'string'],
            'delete_profile_image' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Name is required.',
        ];
    }
}
