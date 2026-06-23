<?php

namespace App\Http\Requests\Order;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UploadOrderReceiptRequest extends FormRequest
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
            'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:20480'],
            'tmp_receipt' => ['nullable', 'string', 'starts_with:tmp-'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'receipt.mimes' => 'Receipt file must be PDF, JPG, or PNG.',
            'receipt.max' => 'Receipt file must not exceed 20MB.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->hasFile('receipt') && ! $this->filled('tmp_receipt')) {
                $validator->errors()->add('receipt', 'A receipt file is required.');
            }
        });
    }
}
