<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexPaymentTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payment_gateways.view_transactions') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'after_id' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', Rule::in(['payment', 'disbursement', 'refund', 'transfer'])],
            'status' => ['nullable', 'string', Rule::in(['success', 'pending', 'failed', 'voided', 'reversed'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}
