<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendTestWhatsAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['master', 'admin']) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'to' => ['required', 'string', 'max:30'],
            'template' => ['required', 'string', 'max:100'],
            'lang' => ['nullable', 'string', 'max:10'],
            'params' => ['nullable', 'array', 'max:10'],
            'params.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to.required' => 'Recipient phone number is required.',
            'template.required' => 'Template name is required.',
        ];
    }
}
