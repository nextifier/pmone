<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TestProjectPaymentGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Same permission gate as creating/updating a gateway — testing
        // a credential is meaningful only to staff who can persist it.
        return ($this->user()?->can('payment_gateways.create') ?? false)
            || ($this->user()?->can('payment_gateways.update') ?? false);
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::in(['xendit'])],
            'mode' => ['required', 'string', Rule::in(['live', 'test'])],
            'secret_key' => ['required', 'string', 'max:500'],
            'webhook_token' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'secret_key.required' => 'Secret key is required for the test connection.',
            'provider.in' => 'Provider not supported. Currently only "xendit" is allowed.',
        ];
    }
}
