<?php

namespace App\Http\Requests\Reservation;

use Illuminate\Foundation\Http\FormRequest;

class ManualRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reservations.refund') ?? false;
    }

    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'max:2000'],
            'bank_reference' => ['nullable', 'string', 'max:200'],
        ];
    }
}
