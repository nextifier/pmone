<?php

namespace App\Http\Requests\PromoCode;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Email;

class UpdatePromoCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('promo_codes.update') ?? false;
    }

    public function rules(): array
    {
        $codeId = $this->route('code')?->id;

        return [
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_email' => ['nullable', 'integer', 'min:1'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['boolean'],
            'issued_to_email' => ['nullable', Email::default(), 'max:255'],
            'metadata' => ['nullable', 'array'],
            'event_id' => ['nullable', 'exists:events,id'],
            // Code itself is typically immutable, but allow rename with uniqueness check
            'code' => [
                'sometimes', 'required', 'string', 'max:60', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('promo_codes', 'code')->ignore($codeId),
            ],
        ];
    }
}
