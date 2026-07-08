<?php

namespace App\Http\Resources;

use App\Models\Attendee;
use App\Models\CustomField;
use App\Support\FormFieldTypes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public attendee shape (e-ticket holder). The qr_token is the access key, so
 * it is only exposed when the attendee is fetched through an authorized context.
 *
 * @mixin Attendee
 */
class AttendeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'qr_token' => $this->qr_token,
            'is_personalized' => $this->personalized_at !== null,
            'is_checked_in' => $this->checked_in_at !== null,
            'has_account' => $this->claimed_by_user_id !== null,
            'checked_in_at' => $this->checked_in_at,
            'reprint_count' => $this->reprint_count,
            'ticket' => $this->whenLoaded('ticket', fn () => [
                'id' => $this->ticket->id,
                'slug' => $this->ticket->slug,
                'kind' => $this->ticket->kind?->value,
                'title' => $this->ticket->getTranslation('title', app()->getLocale(), false),
                'tier' => $this->ticket->tier,
            ]),
            'order' => $this->whenLoaded('ticketOrderItem', fn () => [
                'number' => $this->ticketOrderItem->ticketOrder?->order_number,
                'ulid' => $this->ticketOrderItem->ticketOrder?->ulid,
                'source' => $this->ticketOrderItem->ticketOrder?->source,
            ]),
            'day' => $this->whenLoaded('ticketOrderItem', fn () => $this->ticketOrderItem->selectedEventDay ? [
                'id' => $this->ticketOrderItem->selectedEventDay->id,
                'label' => $this->ticketOrderItem->selectedEventDay->label,
                'date' => $this->ticketOrderItem->selectedEventDay->date?->toDateString(),
            ] : null),
            'session' => $this->whenLoaded('ticketOrderItem', fn () => $this->ticketOrderItem->ticketSession ? [
                'id' => $this->ticketOrderItem->ticketSession->id,
                'label' => $this->ticketOrderItem->ticketSession->label,
            ] : null),
            // Ticket-registration answers keyed by field ulid (logical values,
            // scalar wrapping already unwrapped). Only when eager-loaded.
            'registration_answers' => $this->whenLoaded('customFieldValues', fn () => $this->customFieldValues
                ->filter(fn ($value) => $value->customField?->context === CustomField::CONTEXT_TICKET_REGISTRATION)
                ->mapWithKeys(fn ($value) => [
                    $value->customField->ulid => FormFieldTypes::normalizeStored($value->customField->type, $value->value),
                ])),
        ];
    }
}
