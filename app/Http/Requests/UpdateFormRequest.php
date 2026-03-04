<?php

namespace App\Http\Requests;

use App\Models\Form;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $form = $this->route('form');
        $formId = $form ? $form->id : null;

        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:forms,slug,'.$formId],
            'settings' => ['nullable', 'array'],
            'settings.confirmation_message' => ['nullable', 'string', 'max:1000'],
            'settings.redirect_url' => ['nullable', 'url', 'max:2048'],
            'settings.require_email' => ['nullable', 'boolean'],
            'settings.prevent_duplicate' => ['nullable', 'boolean'],
            'settings.prevent_duplicate_by' => ['nullable', 'string', 'in:email,fingerprint,both'],
            'status' => ['sometimes', 'string', 'in:'.implode(',', Form::allowedStatuses())],
            'is_active' => ['sometimes', 'boolean'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after_or_equal:opens_at'],
            'response_limit' => ['nullable', 'integer', 'min:1'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'tmp_cover_image' => ['nullable', 'string'],
            'delete_cover_image' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.unique' => 'This slug is already taken.',
            'closes_at.after_or_equal' => 'Close date must be after or equal to open date.',
            'response_limit.min' => 'Response limit must be at least 1.',
        ];
    }
}
