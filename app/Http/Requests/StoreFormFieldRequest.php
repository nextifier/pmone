<?php

namespace App\Http\Requests;

use App\Models\CustomField;
use App\Support\FormFieldTypes;
use Illuminate\Foundation\Http\FormRequest;

class StoreFormFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Plain-string label/placeholder/help_text payloads (the pre-translatable
     * admin builder) coerce to an English translation map so one payload shape
     * reaches the model.
     */
    protected function prepareForValidation(): void
    {
        foreach (['label', 'placeholder', 'help_text'] as $attribute) {
            $value = $this->input($attribute);

            if (is_string($value)) {
                $this->merge([$attribute => ['en' => $value]]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:'.implode(',', CustomField::allowedTypes())],
            'label' => ['required', 'array'],
            'label.en' => ['required', 'string', 'max:255'],
            'label.*' => ['nullable', 'string', 'max:255'],
            'placeholder' => ['nullable', 'array'],
            'placeholder.*' => ['nullable', 'string', 'max:255'],
            'help_text' => ['nullable', 'array'],
            'help_text.*' => ['nullable', 'string', 'max:1000'],
            'options' => ['nullable', 'array', 'required_if:type,'.implode(',', FormFieldTypes::OPTION_TYPES)],
            'options.*.value' => ['required_with:options', 'string'],
            'options.*.label' => ['required_with:options', 'string'],
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
            'settings.multiple' => ['nullable', 'boolean'],
            'settings.step' => ['nullable', 'numeric', 'gt:0'],
            'settings.max' => ['nullable', 'integer', 'min:2', 'max:10'],
            'settings.min_label' => ['nullable', 'string', 'max:100'],
            'settings.max_label' => ['nullable', 'string', 'max:100'],
            'settings.description' => ['nullable', 'string', 'max:10000'],
            'settings.param_key' => ['nullable', 'string', 'max:50', 'regex:/^[a-zA-Z0-9_-]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Field type is required.',
            'type.in' => 'Invalid field type.',
            'label.required' => 'Field label is required.',
            'label.en.required' => 'Field label is required.',
        ];
    }
}
