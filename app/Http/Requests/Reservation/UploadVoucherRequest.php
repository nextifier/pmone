<?php

namespace App\Http\Requests\Reservation;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UploadVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reservations.upload_voucher') ?? false;
    }

    public function rules(): array
    {
        return [
            'voucher' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:20480'],
            'tmp_voucher' => ['nullable', 'string', 'starts_with:tmp-'],
        ];
    }

    public function messages(): array
    {
        return [
            'voucher.mimes' => 'Voucher file must be PDF, JPG, or PNG.',
            'voucher.max' => 'Voucher file must not exceed 20MB.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->hasFile('voucher') && ! $this->filled('tmp_voucher')) {
                $validator->errors()->add('voucher', 'A voucher file is required.');
            }
        });
    }
}
