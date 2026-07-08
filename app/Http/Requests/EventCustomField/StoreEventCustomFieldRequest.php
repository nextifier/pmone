<?php

namespace App\Http\Requests\EventCustomField;

use App\Models\CustomField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventCustomFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('event_custom_fields.create') ?? false;
    }

    /**
     * Accept translatable objects {en, id, ja, ko, zh} for label, placeholder,
     * and help_text. Plain strings are coerced to {en: "..."} so older callers
     * keep working. Context defaults to business matching (the pre-unification
     * behavior of this endpoint).
     */
    protected function prepareForValidation(): void
    {
        foreach (['label', 'placeholder', 'help_text'] as $attribute) {
            if (is_string($this->input($attribute))) {
                $this->merge([$attribute => ['en' => $this->input($attribute)]]);
            }
        }

        if (! $this->filled('context')) {
            $this->merge(['context' => CustomField::CONTEXT_BUSINESS_MATCHING]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $context = (string) $this->input('context', CustomField::CONTEXT_BUSINESS_MATCHING);

        return [
            'context' => ['required', Rule::in([
                CustomField::CONTEXT_BUSINESS_MATCHING,
                CustomField::CONTEXT_TICKET_REGISTRATION,
            ])],
            'label' => ['required', 'array'],
            'label.en' => ['required', 'string', 'max:255'],
            'label.id' => ['nullable', 'string', 'max:255'],
            'label.ja' => ['nullable', 'string', 'max:255'],
            'label.ko' => ['nullable', 'string', 'max:255'],
            'label.zh' => ['nullable', 'string', 'max:255'],
            'placeholder' => ['nullable', 'array'],
            'placeholder.*' => ['nullable', 'string', 'max:255'],
            'help_text' => ['nullable', 'array'],
            'help_text.*' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', Rule::in(CustomField::allowedTypesFor($context))],
            'options' => ['nullable', 'array'],
            'options.*' => [function (string $attribute, mixed $value, \Closure $fail) {
                $isPlainString = is_string($value) && mb_strlen($value) <= 255;
                $isPair = is_array($value)
                    && is_string($value['value'] ?? null) && mb_strlen($value['value']) <= 255
                    && (is_string($value['label'] ?? null) || is_array($value['label'] ?? null));

                if (! $isPlainString && ! $isPair) {
                    $fail('Each option must be a string or a {value, label} pair.');
                }
            }],
            'required' => ['sometimes', 'boolean'],
            'validation' => ['nullable', 'array'],
            'validation.required' => ['nullable', 'boolean'],
            'validation.min' => ['nullable', 'integer'],
            'validation.max' => ['nullable', 'integer'],
            'validation.min_selections' => ['nullable', 'integer', 'min:0'],
            'validation.max_selections' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'settings' => ['nullable', 'array'],
        ];
    }

    /**
     * Merge the legacy top-level `required` flag into validation.required and
     * strip request-only keys so the payload maps straight onto CustomField.
     *
     * @return array<string, mixed>
     */
    public function fieldAttributes(): array
    {
        $data = $this->validated();

        if (array_key_exists('required', $data)) {
            $data['validation'] = array_merge($data['validation'] ?? [], [
                'required' => (bool) $data['required'],
            ]);
            unset($data['required']);
        }

        unset($data['context']);

        return $data;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.en.required' => 'Label (English) is required.',
        ];
    }
}
