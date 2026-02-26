<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'edition_number' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:50000'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'location' => ['nullable', 'string', 'max:500'],
            'location_link' => ['nullable', 'url', 'max:1000'],
            'hall' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', Rule::in(['draft', 'published', 'archived', 'cancelled'])],
            'visibility' => ['sometimes', 'string', Rule::in(['public', 'private'])],
            'settings' => ['nullable', 'array'],
            'custom_fields' => ['nullable', 'array'],
            'gross_area' => ['nullable', 'numeric', 'min:0'],
            'tmp_poster_image' => ['nullable', 'string'],
            'order_form_deadline' => ['nullable', 'date'],
            'promotion_post_deadline' => ['nullable', 'date'],
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
            'title.required' => 'Event title is required.',
            'title.max' => 'Event title cannot exceed 255 characters.',
            'end_date.after_or_equal' => 'End date must be after or equal to start date.',
            'location_link.url' => 'Location link must be a valid URL.',
            'status.in' => 'Invalid status. Must be one of: draft, published, archived, cancelled.',
            'visibility.in' => 'Invalid visibility. Must be one of: public, private.',
        ];
    }
}
