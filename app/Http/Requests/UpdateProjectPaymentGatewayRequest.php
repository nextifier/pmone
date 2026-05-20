<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectPaymentGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payment_gateways.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'nullable', 'string', 'max:100'],
            'mode' => ['sometimes', 'string', Rule::in(['live', 'test'])],
            'is_active' => ['sometimes', 'boolean'],
            // Empty/missing means "keep existing value" — handled in controller.
            'secret_key' => ['sometimes', 'nullable', 'string', 'max:500'],
            'public_key' => ['sometimes', 'nullable', 'string', 'max:500'],
            'webhook_token' => ['sometimes', 'nullable', 'string', 'max:500'],
            'config' => ['sometimes', 'nullable', 'array'],
            'config.success_redirect_url' => ['nullable', 'url', 'max:500'],
            'config.failure_redirect_url' => ['nullable', 'url', 'max:500'],
        ];
    }
}
