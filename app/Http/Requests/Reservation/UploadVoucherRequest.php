<?php

namespace App\Http\Requests\Reservation;

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
            'voucher' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:20480'],
        ];
    }

    public function messages(): array
    {
        return [
            'voucher.mimes' => 'Voucher file must be PDF, JPG, or PNG.',
            'voucher.max' => 'Voucher file must not exceed 20MB.',
        ];
    }
}
