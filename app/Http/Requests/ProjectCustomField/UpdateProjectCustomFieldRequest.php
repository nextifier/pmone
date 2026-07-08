<?php

namespace App\Http\Requests\ProjectCustomField;

use App\Models\CustomField;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectCustomFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Accept the legacy CustomFieldsManager payload alongside the centralized
     * shape: a plain-string label is coerced to {en: "..."}, the `year_select`
     * alias becomes select + settings.options_preset=years, and the boolean
     * `is_required` flag maps to validation.required (no rule of its own, so
     * it never reaches the service payload).
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        if (is_string($this->label)) {
            $merge['label'] = ['en' => $this->label];
        }

        if ($this->input('type') === 'year_select') {
            $merge['type'] = CustomField::TYPE_SELECT;
            $merge['settings'] = array_merge((array) $this->input('settings', []), ['options_preset' => 'years']);
            $merge['options'] = null;
        }

        if ($this->has('is_required')) {
            $merge['validation'] = array_merge(
                (array) $this->input('validation', []),
                ['required' => $this->boolean('is_required')],
            );
        }

        if ($merge !== []) {
            $this->merge($merge);
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
            'type' => ['sometimes', 'required', 'string', Rule::in(CustomField::allowedTypesFor(CustomField::CONTEXT_BRAND))],
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
            'label.en.required_with' => 'Label is required.',
            'type.in' => 'Invalid field type for brand fields.',
        ];
    }
}
