<?php

namespace App\Http\Requests\PromoCode;

use Illuminate\Foundation\Http\FormRequest;

class StorePromoCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('promo_codes.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:60', 'unique:promo_codes,code', 'regex:/^[A-Za-z0-9_-]+$/'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_email' => ['nullable', 'integer', 'min:1'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['boolean'],
            'issued_to_email' => ['nullable', 'email', 'max:255'],
            'metadata' => ['nullable', 'array'],
            'event_id' => ['nullable', 'exists:events,id'],
        ];
    }
}
