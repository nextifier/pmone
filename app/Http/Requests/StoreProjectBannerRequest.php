<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectBannerRequest extends FormRequest
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
            'type' => ['required', Rule::in(['image', 'text', 'image_text'])],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'link' => ['nullable', 'string', 'max:2000'],
            'cta_label' => ['nullable', 'string', 'max:120'],
            'aspect_ratio' => ['nullable', Rule::in(['1:1', '16:9', '9:16', '4:5', '2:1', '4:1'])],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'start_time' => ['nullable', 'date'],
            'end_time' => ['nullable', 'date', 'after_or_equal:start_time'],
            'tmp_image' => ['nullable', 'string', 'required_if:type,image'],
            'more_details' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end_time.after_or_equal' => 'The end time must be after the start time.',
            'tmp_image.required_if' => 'An image is required for image banners.',
        ];
    }
}
