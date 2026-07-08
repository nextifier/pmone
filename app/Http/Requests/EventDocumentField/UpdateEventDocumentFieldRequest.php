<?php

namespace App\Http\Requests\EventDocumentField;

use App\Models\CustomField;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventDocumentFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Accept a translatable label object {en, id, ja, ko, zh}. A plain string is
     * coerced to {en: "..."} below so simpler callers keep working.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->label)) {
            $this->merge(['label' => ['en' => $this->label]]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'required', 'array'],
            'label.en' => ['required_with:label', 'string', 'max:255'],
            'label.id' => ['nullable', 'string', 'max:255'],
            'label.ja' => ['nullable', 'string', 'max:255'],
            'label.ko' => ['nullable', 'string', 'max:255'],
            'label.zh' => ['nullable', 'string', 'max:255'],
            'type' => ['sometimes', 'required', 'string', Rule::in(CustomField::allowedTypesFor(CustomField::CONTEXT_DOCUMENT))],
            'placeholder' => ['nullable'],
            'help_text' => ['nullable'],
            'options' => ['nullable', 'array'],
            'options.*' => [$this->optionRule()],
            'validation' => ['nullable', 'array'],
            'validation.required' => ['nullable', 'boolean'],
            'validation.min' => ['nullable', 'integer'],
            'validation.max' => ['nullable', 'integer'],
            'validation.min_selections' => ['nullable', 'integer', 'min:0'],
            'validation.max_selections' => ['nullable', 'integer', 'min:1'],
            'validation.max_file_size' => ['nullable', 'integer', 'min:1'],
            'validation.max_files' => ['nullable', 'integer', 'min:1', 'max:10'],
            'validation.allowed_file_types' => ['nullable', 'array'],
            'validation.allowed_file_types.*' => ['string'],
            'settings' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Options may be plain strings or {value, label} pairs; the service
     * canonicalizes both via FormFieldTypes::normalizeOptions.
     */
    protected function optionRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && $value !== '') {
                return;
            }

            if (is_array($value) && is_scalar($value['value'] ?? null)) {
                return;
            }

            $fail('Each option must be a string or a {value, label} pair.');
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.en.required_with' => 'Label (English) is required.',
            'type.in' => 'Invalid field type for document fields.',
        ];
    }
}
