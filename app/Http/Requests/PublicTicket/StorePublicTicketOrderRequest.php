<?php

namespace App\Http\Requests\PublicTicket;

use Illuminate\Foundation\Http\FormRequest;

class StorePublicTicketOrderRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.ticket_id' => ['required', 'integer', 'exists:tickets,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.ticket_session_id' => ['nullable', 'integer', 'exists:ticket_sessions,id'],
            'items.*.selected_event_day_id' => ['nullable', 'integer', 'exists:event_days,id'],

            'buyer_name' => ['required', 'string', 'max:255'],
            'buyer_email' => ['required', 'email', 'max:255'],
            'buyer_phone' => ['required', 'string', 'max:50'],

            'also_attending' => ['sometimes', 'boolean'],
            'promo_code' => ['nullable', 'string', 'max:60'],
            'access_code' => ['nullable', 'string', 'max:60'],
            'accept_terms' => ['accepted'],
            'origin' => ['nullable', 'url', 'max:255'],

            // Business matching intake (buyer answers, stored on their User).
            'business_matching' => ['sometimes', 'array'],
            'business_matching.opt_in' => ['sometimes', 'boolean'],
            'business_matching.responses' => ['sometimes', 'array'],
            'business_matching.responses.*.custom_field_id' => ['required_with:business_matching.responses', 'integer', 'exists:event_custom_fields,id'],
            'business_matching.responses.*.value' => ['nullable'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'accept_terms.accepted' => 'You must accept the terms and conditions to continue.',
        ];
    }
}
