<?php

namespace App\Http\Requests\GoogleAnalytics;

use Illuminate\Foundation\Http\FormRequest;

class SyncAnalyticsRequest extends FormRequest
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
            'property_id' => ['nullable', 'integer', 'exists:ga_properties,id'],
            'days' => ['nullable', 'integer', 'min:1', 'max:90'],
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
            'property_id.exists' => 'The selected property does not exist.',
            'days.max' => 'Maximum sync period is 90 days.',
        ];
    }
}
