<?php

namespace App\Http\Requests;

use App\Rules\HoneypotPassed;
use Illuminate\Foundation\Http\FormRequest;

class ContactFormSubmitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Public endpoint, no authentication required
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project_username' => ['required', 'string', 'exists:projects,username', new HoneypotPassed],
            'subject' => ['nullable', 'string', 'max:255'],
            'data' => ['required', 'array', 'min:1'],
            'data.*' => ['nullable'], // Allow dynamic fields
            'data.name' => ['required', 'string', 'max:255'],
            'data.email' => ['required', 'email', 'max:255'],
            'data.phone' => ['required', 'string', 'max:50'],
            'data.message' => ['sometimes', 'string', 'max:5000'],

            // Honeypot fields (should not be filled by real users)
            'website' => ['nullable', 'max:0'],
            '_token_time' => ['nullable', 'string'],
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
            'project_username.required' => 'Project username is required.',
            'project_username.exists' => 'Project not found.',
            'subject.max' => 'Subject must not exceed 255 characters.',
            'data.required' => 'Form data is required.',
            'data.array' => 'Form data must be an array.',
            'data.min' => 'Form data must contain at least one field.',
            'data.name.required' => 'Name is required.',
            'data.name.max' => 'Name must not exceed 255 characters.',
            'data.email.required' => 'Email is required.',
            'data.email.email' => 'Please provide a valid email address.',
            'data.phone.required' => 'Phone number is required.',
            'data.phone.max' => 'Phone number must not exceed 50 characters.',
            'data.message.max' => 'Message must not exceed 5000 characters.',
            'website.max' => 'Form submission failed. Please try again.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitize form data
        if ($this->has('data') && is_array($this->data)) {
            $sanitizedData = [];
            foreach ($this->data as $key => $value) {
                // Skip sanitization for message field to preserve line breaks
                if ($key === 'message') {
                    $sanitizedData[$key] = $value;
                } else {
                    // Strip tags and trim for other fields
                    $sanitizedData[$key] = is_string($value) ? strip_tags(trim($value)) : $value;
                }
            }
            $this->merge(['data' => $sanitizedData]);
        }
    }
}
