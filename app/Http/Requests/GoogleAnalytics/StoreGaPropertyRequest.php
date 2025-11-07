<?php

namespace App\Http\Requests\GoogleAnalytics;

use Illuminate\Foundation\Http\FormRequest;

class StoreGaPropertyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasRole(['master', 'admin']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'property_id' => ['required', 'string', 'max:255', 'unique:ga_properties,property_id'],
            'is_active' => ['nullable', 'boolean'],
            'sync_frequency' => ['nullable', 'integer', 'min:5', 'max:60'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'tmp_profile_image' => ['nullable', 'string', 'regex:/^tmp-[a-zA-Z0-9._]+$/'],
            'delete_profile_image' => ['nullable', 'boolean'],
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
            'name.required' => 'Property name is required.',
            'property_id.required' => 'GA4 Property ID is required.',
            'property_id.unique' => 'This property ID already exists.',
            'sync_frequency.min' => 'Sync frequency must be at least 5 minutes.',
            'sync_frequency.max' => 'Sync frequency cannot exceed 60 minutes.',
            'tags.*.max' => 'Each tag must not exceed 100 characters.',
            'tmp_profile_image.regex' => 'Invalid profile image format.',
        ];
    }
}
