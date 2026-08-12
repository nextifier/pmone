<?php

namespace App\Http\Requests\EventCustomField;

class UpdateEventCustomFieldRequest extends StoreEventCustomFieldRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('event_custom_fields.update') ?? false;
    }

    /**
     * Relaxes the parent's create rules to a patch.
     *
     * Inheriting them unchanged made this the only update endpoint of the four
     * custom-field contexts that demanded a full payload: sending just
     * `{label}` failed on `context`, `label.en` and `type` all being required.
     * `context` is dropped rather than relaxed, because
     * `CustomFieldService::update()` unsets it anyway - a field cannot change
     * context after create.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        unset($rules['context']);

        $rules['label'] = ['sometimes', 'required', 'array'];
        $rules['label.en'] = ['required_with:label', 'string', 'max:255'];
        $rules['type'] = ['sometimes', 'required', ...array_slice((array) $rules['type'], 1)];

        return $rules;
    }
}
