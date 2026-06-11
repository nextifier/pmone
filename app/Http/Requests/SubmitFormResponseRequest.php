<?php

namespace App\Http\Requests;

use App\Rules\HoneypotPassed;
use Illuminate\Foundation\Http\FormRequest;

class SubmitFormResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'responses' => ['present', 'array', new HoneypotPassed],
            'respondent_email' => ['nullable', 'email', 'max:255'],
            'browser_fingerprint' => ['nullable', 'string', 'max:255'],

            // Honeypot fields (should not be filled by real users)
            'website' => ['nullable', 'max:0'],
            '_token_time' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'responses.required' => 'Form responses are required.',
            'respondent_email.email' => 'Please provide a valid email address.',
            'website.max' => 'Form submission failed. Please try again.',
        ];
    }
}
