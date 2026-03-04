<?php

namespace App\Http\Requests;

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
            'responses' => ['present', 'array'],
            'respondent_email' => ['nullable', 'email', 'max:255'],
            'browser_fingerprint' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'responses.required' => 'Form responses are required.',
            'respondent_email.email' => 'Please provide a valid email address.',
        ];
    }
}
