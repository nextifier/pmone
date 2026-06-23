<?php

namespace App\Http\Requests\Order;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UploadOrderInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'invoice' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'tmp_invoice' => ['nullable', 'string', 'starts_with:tmp-'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'invoice.mimes' => 'Invoice file must be a PDF.',
            'invoice.max' => 'Invoice file must not exceed 20MB.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->hasFile('invoice') && ! $this->filled('tmp_invoice')) {
                $validator->errors()->add('invoice', 'An invoice file is required.');
            }
        });
    }
}
