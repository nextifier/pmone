<?php

namespace App\Http\Requests;

use App\Enums\BoothType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'document_type' => ['required', 'string', Rule::in(['file_upload', 'checkbox_agreement', 'text_input'])],
            'is_required' => ['sometimes', 'boolean'],
            'blocks_next_step' => ['sometimes', 'boolean'],
            'submission_deadline' => ['nullable', 'date'],
            'booth_types' => ['nullable', 'array'],
            'booth_types.*' => ['string', Rule::in(array_column(BoothType::cases(), 'value'))],
            'settings' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Document title is required.',
            'document_type.required' => 'Document type is required.',
            'document_type.in' => 'Invalid document type. Must be file_upload, checkbox_agreement, or text_input.',
            'booth_types.*.in' => 'Invalid booth type specified.',
        ];
    }
}
