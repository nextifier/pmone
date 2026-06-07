<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFaqRequest extends FormRequest
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
            'question' => ['required', 'array'],
            'question.en' => ['required', 'string', 'max:1000'],
            'question.id' => ['nullable', 'string', 'max:1000'],
            'question.ja' => ['nullable', 'string', 'max:1000'],
            'question.ko' => ['nullable', 'string', 'max:1000'],
            'question.zh' => ['nullable', 'string', 'max:1000'],

            'answer' => ['required', 'array'],
            'answer.en' => ['required', 'string', 'max:20000'],
            'answer.id' => ['nullable', 'string', 'max:20000'],
            'answer.ja' => ['nullable', 'string', 'max:20000'],
            'answer.ko' => ['nullable', 'string', 'max:20000'],
            'answer.zh' => ['nullable', 'string', 'max:20000'],

            'is_active' => ['sometimes', 'boolean'],
            'settings' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'question.en.required' => 'Question (English) is required.',
            'answer.en.required' => 'Answer (English) is required.',
        ];
    }
}
