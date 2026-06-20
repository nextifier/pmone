<?php

namespace App\Http\Requests\EventCustomField;

use App\Support\FormFieldTypes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventCustomFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('event_custom_fields.create') ?? false;
    }

    /**
     * Accept a translatable label object {en, id, ja, ko, zh}. A plain string is
     * coerced to {en: "..."} below so older callers keep working.
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
            'label' => ['required', 'array'],
            'label.en' => ['required', 'string', 'max:255'],
            'label.id' => ['nullable', 'string', 'max:255'],
            'label.ja' => ['nullable', 'string', 'max:255'],
            'label.ko' => ['nullable', 'string', 'max:255'],
            'label.zh' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(FormFieldTypes::all())],
            'options' => ['nullable', 'array'],
            'options.*' => ['string', 'max:255'],
            'required' => ['sometimes', 'boolean'],
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
            'label.en.required' => 'Label (English) is required.',
        ];
    }
}
