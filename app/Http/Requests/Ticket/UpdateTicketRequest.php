<?php

namespace App\Http\Requests\Ticket;

/**
 * Reuses StoreTicketRequest's rules/messages. Differs only in the permission
 * checked and in resolving `kind` from the route-bound ticket when the field
 * is omitted from a partial update.
 */
class UpdateTicketRequest extends StoreTicketRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tickets.update') ?? false;
    }

    protected function resolveKind(): ?string
    {
        return $this->input('kind') ?? $this->route('ticket')?->kind?->value;
    }
}
