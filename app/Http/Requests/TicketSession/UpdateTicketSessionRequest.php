<?php

namespace App\Http\Requests\TicketSession;

/**
 * Reuses StoreTicketSessionRequest's rules and the add-on / event-range checks.
 * Differs only in the permission checked.
 */
class UpdateTicketSessionRequest extends StoreTicketSessionRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('ticket_sessions.update') ?? false;
    }
}
