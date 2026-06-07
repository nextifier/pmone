<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFaqRequest extends FormRequest
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
            'question' => ['sometimes', 'array'],
            'question.en' => ['required_with:question', 'string', 'max:1000'],
            'question.id' => ['nullable', 'string', 'max:1000'],
            'question.ja' => ['nullable', 'string', 'max:1000'],
            'question.ko' => ['nullable', 'string', 'max:1000'],
            'question.zh' => ['nullable', 'string', 'max:1000'],

            'answer' => ['sometimes', 'array'],
            'answer.en' => ['required_with:answer', 'string', 'max:20000'],
            'answer.id' => ['nullable', 'string', 'max:20000'],
            'answer.ja' => ['nullable', 'string', 'max:20000'],
            'answer.ko' => ['nullable', 'string', 'max:20000'],
            'answer.zh' => ['nullable', 'string', 'max:20000'],

            'is_active' => ['sometimes', 'boolean'],
            'settings' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'question.en.required_with' => 'Question (English) is required.',
            'answer.en.required_with' => 'Answer (English) is required.',
        ];
    }
}
