<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGuestRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],

            'title' => ['nullable', 'array'],
            'title.en' => ['nullable', 'string', 'max:255'],
            'title.id' => ['nullable', 'string', 'max:255'],

            'bio' => ['nullable', 'array'],
            'bio.en' => ['nullable', 'string', 'max:50000'],
            'bio.id' => ['nullable', 'string', 'max:50000'],

            'organization' => ['nullable', 'string', 'max:255'],

            'status' => ['sometimes', 'string', 'in:active,inactive'],
            'visibility' => ['sometimes', 'string', 'in:public,private'],
            'is_featured' => ['sometimes', 'boolean'],

            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],

            'more_details' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],

            'links' => ['nullable', 'array'],
            'links.*.label' => ['required_with:links.*.url', 'string', 'max:100'],
            'links.*.url' => ['required_with:links.*.label', 'string', 'max:500'],

            'tmp_profile_image' => ['nullable', 'string'],
            'delete_profile_image' => ['sometimes', 'boolean'],
        ];
    }
}
