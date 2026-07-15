<?php

namespace App\Http\Requests\PublicTicket;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Email;

class JoinWaitlistRequest extends FormRequest
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
            'ticket_id' => ['required', 'integer', 'exists:tickets,id'],
            'email' => ['required', Email::default(), 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
