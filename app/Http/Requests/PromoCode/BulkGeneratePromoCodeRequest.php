<?php

namespace App\Http\Requests\PromoCode;

use Illuminate\Foundation\Http\FormRequest;

class BulkGeneratePromoCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('promotions.bulk_generate_codes') ?? false;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'prefix' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]*$/'],
            'length' => ['nullable', 'integer', 'min:4', 'max:40'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_email' => ['nullable', 'integer', 'min:1'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
