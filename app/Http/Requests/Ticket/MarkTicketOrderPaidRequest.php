<?php

namespace App\Http\Requests\Ticket;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarkTicketOrderPaidRequest extends FormRequest
{
    /**
     * Payment channels that have a logo in the admin table + invoice/receipt
     * PDFs. Mirror of `App\Services\Pdf\ResolvesPdfBranding::channelLogoFile()`
     * and `frontend/app/lib/payment-method-logos.ts`, so a manually-selected
     * channel always renders a logo in the Payment column.
     *
     * @var array<int, string>
     */
    public const ALLOWED_CHANNELS = [
        'BCA', 'BNI', 'BRI', 'MANDIRI', 'PERMATA', 'BSI', 'BSS', 'CIMB', 'CIMB_NIAGA',
        'BJB', 'BNC', 'NEOBANK', 'MUAMALAT', 'GOPAY', 'OVO', 'DANA', 'SHOPEEPAY',
        'LINKAJA', 'JENIUSPAY', 'NEXCASH', 'ASTRAPAY', 'QRIS', 'VISA', 'MASTERCARD',
        'AMEX', 'JCB', 'DD_BRI', 'BRI_DIRECT_DEBIT',
    ];

    /**
     * Authorization is enforced by the route middleware `can:tickets.mark_paid`.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize the channel to the upper-cased code the logo maps use.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('payment_channel')) {
            $this->merge([
                'payment_channel' => strtoupper(trim((string) $this->input('payment_channel'))),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payment_channel' => ['required', 'string', Rule::in(self::ALLOWED_CHANNELS)],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_channel.required' => 'Please select the payment channel used.',
            'payment_channel.in' => 'The selected payment channel is not supported.',
            'note.max' => 'Note must be 1000 characters or fewer.',
        ];
    }
}
