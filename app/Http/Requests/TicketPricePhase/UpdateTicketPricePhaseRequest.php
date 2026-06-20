<?php

namespace App\Http\Requests\TicketPricePhase;

/**
 * Reuses StoreTicketPricePhaseRequest's rules and overlap check (which already
 * excludes the route-bound phase on update). Differs only in the permission.
 */
class UpdateTicketPricePhaseRequest extends StoreTicketPricePhaseRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('ticket_price_phases.update') ?? false;
    }
}
