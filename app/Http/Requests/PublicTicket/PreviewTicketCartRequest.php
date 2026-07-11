<?php

namespace App\Http\Requests\PublicTicket;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class PreviewTicketCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.ticket_id' => ['required', 'integer', 'exists:tickets,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.ticket_session_id' => ['nullable', 'integer', 'exists:ticket_sessions,id'],
            'items.*.selected_event_day_id' => ['nullable', 'integer', 'exists:event_days,id'],
            'promo_code' => ['nullable', 'string', 'max:64'],
            'access_code' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * Cap the total cart quantity, mirroring StorePublicTicketOrderRequest, so
     * a preview request can't be used to force hundreds of lines through the
     * pricing engine.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $totalQuantity = collect((array) $this->input('items', []))
                ->sum(fn ($item) => (int) ($item['quantity'] ?? 0));

            if ($totalQuantity > 200) {
                $validator->errors()->add('items', 'The total ticket quantity across all items may not exceed 200.');
            }
        });
    }
}
