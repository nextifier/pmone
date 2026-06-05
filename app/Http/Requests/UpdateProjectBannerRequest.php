<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectBannerRequest extends FormRequest
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
            'placement' => ['sometimes', 'string', 'max:50'],
            'type' => ['sometimes', Rule::in(['image', 'text', 'image_text'])],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'link' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'cta_label' => ['sometimes', 'nullable', 'string', 'max:120'],
            'aspect_ratio' => ['sometimes', 'nullable', Rule::in(['1:1', '16:9', '9:16', '4:5', '2:1', '4:1'])],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'start_time' => ['sometimes', 'nullable', 'date'],
            'end_time' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_time'],
            'more_details' => ['sometimes', 'nullable', 'array'],
            'settings' => ['sometimes', 'nullable', 'array'],
            'tmp_image' => ['nullable', 'string'],
            'delete_image' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end_time.after_or_equal' => 'The end time must be after the start time.',
        ];
    }
}
