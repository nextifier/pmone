<?php

namespace App\Http\Requests\EventDay;

/**
 * Reuses StoreEventDayRequest's rules (the scoped uniqueness already ignores
 * the route-bound day on update). Differs only in the permission checked.
 */
class UpdateEventDayRequest extends StoreEventDayRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('event_days.update') ?? false;
    }
}
