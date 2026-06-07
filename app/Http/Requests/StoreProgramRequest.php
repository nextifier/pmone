<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProgramRequest extends FormRequest
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
            'title' => ['required', 'array'],
            'title.en' => ['required', 'string', 'max:500'],
            'title.id' => ['nullable', 'string', 'max:500'],
            'title.ja' => ['nullable', 'string', 'max:500'],
            'title.ko' => ['nullable', 'string', 'max:500'],
            'title.zh' => ['nullable', 'string', 'max:500'],

            'description' => ['nullable', 'array'],
            'description.en' => ['nullable', 'string', 'max:5000'],
            'description.id' => ['nullable', 'string', 'max:5000'],
            'description.ja' => ['nullable', 'string', 'max:5000'],
            'description.ko' => ['nullable', 'string', 'max:5000'],
            'description.zh' => ['nullable', 'string', 'max:5000'],

            'icon' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'settings' => ['nullable', 'array'],

            'tmp_image' => ['nullable', 'string'],
            'delete_image' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.en.required' => 'Title (English) is required.',
        ];
    }
}
