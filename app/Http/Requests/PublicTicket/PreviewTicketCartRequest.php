<?php

namespace App\Http\Requests\PublicTicket;

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
            'items' => ['required', 'array', 'min:1'],
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
}
