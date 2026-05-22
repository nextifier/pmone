<?php

namespace App\Http\Requests;

use App\Enums\Payment\CheckoutMethod;
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
            // A "coming soon" method renders in the UI but is rejected here so
            // it cannot be persisted until its checkout flow is implemented.
            'checkout_method' => ['sometimes', 'string', Rule::in(CheckoutMethod::availableValues())],
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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mode.in' => 'Mode must be "live" or "test".',
            'checkout_method.in' => 'That checkout method is not available yet.',
        ];
    }
}
