<?php

namespace App\Http\Requests;

use App\Enums\Payment\CheckoutMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectPaymentGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payment_gateways.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::in(['xendit'])],
            'label' => ['nullable', 'string', 'max:100'],
            'mode' => ['required', 'string', Rule::in(['live', 'test'])],
            // A "coming soon" method renders in the UI but is rejected here so
            // it cannot be persisted until its checkout flow is implemented.
            'checkout_method' => ['sometimes', 'string', Rule::in(CheckoutMethod::availableValues())],
            'is_active' => ['sometimes', 'boolean'],
            'secret_key' => ['required', 'string', 'max:500'],
            'public_key' => ['nullable', 'string', 'max:500'],
            'webhook_token' => ['nullable', 'string', 'max:500'],
            'config' => ['nullable', 'array'],
            'config.success_redirect_url' => ['nullable', 'url', 'max:500'],
            'config.failure_redirect_url' => ['nullable', 'url', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'provider.in' => 'Provider not supported. Currently only "xendit" is allowed.',
            'mode.in' => 'Mode must be "live" or "test".',
            'checkout_method.in' => 'That checkout method is not available yet.',
            'secret_key.required' => 'Secret key is required.',
        ];
    }
}
