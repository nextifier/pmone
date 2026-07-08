<?php

namespace App\Services\Ticket;

use App\Models\Attendee;
use App\Models\Event;
use App\Models\TicketSession;
use App\Support\CustomFieldValidation;
use App\Support\CustomFieldValues;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Staff-side edits to an issued attendee: contact details, moving a single
 * attendee to a different day/session (splitting its order item when shared),
 * and toggling check-in. Stock/capacity stays consistent throughout.
 */
class AttendeeService
{
    public function __construct(private TicketPurchaseService $purchases) {}

    /**
     * Apply a staff edit. $data may contain: name, email, phone,
     * selected_event_day_id, ticket_session_id, checked_in (bool).
     *
     * @param  array<string, mixed>  $data
     */
    public function applyStaffEdit(Attendee $attendee, array $data, Event $event, int $staffId): Attendee
    {
        return DB::transaction(function () use ($attendee, $data, $event, $staffId) {
            $contact = array_intersect_key($data, array_flip(['name', 'email', 'phone']));
            if ($contact !== []) {
                $contact['personalized_at'] = $attendee->personalized_at ?? now();
                $attendee->update($contact);
            }

            if (array_key_exists('selected_event_day_id', $data) || array_key_exists('ticket_session_id', $data)) {
                $this->changeDayOrSession(
                    $attendee,
                    array_key_exists('selected_event_day_id', $data) ? $data['selected_event_day_id'] : '__keep__',
                    array_key_exists('ticket_session_id', $data) ? $data['ticket_session_id'] : '__keep__',
                );
            }

            if (array_key_exists('checked_in', $data)) {
                $this->setCheckIn($attendee, (bool) $data['checked_in'], $staffId, $event->id);
            }

            if (! empty($data['registration']) && is_array($data['registration'])) {
                $this->saveRegistrationResponses($attendee, $event, $data['registration']);
            }

            return $attendee->fresh(['ticket', 'ticketOrderItem.selectedEventDay', 'ticketOrderItem.ticketSession', 'ticketOrderItem.ticketOrder', 'customFieldValues.customField']);
        });
    }

    /**
     * Persist registration answers for an attendee (partial fills allowed):
     * only the provided fields are validated, so `required` never blocks a
     * contact-only edit. Values are type-checked against the event's catalog.
     *
     * @param  array<string, mixed>  $registration  Keyed by field ulid.
     */
    public function saveRegistrationResponses(Attendee $attendee, Event $event, array $registration): void
    {
        $fields = $event->registrationFields()->where('is_active', true)->get();

        $provided = $fields->filter(fn ($field) => array_key_exists($field->ulid, $registration));

        $errors = CustomFieldValidation::errorsFor($provided, $registration, 'registration');
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        CustomFieldValues::store($attendee, $provided, $registration, 'ulid');
    }

    /**
     * Move one attendee to a different day and/or session. When the attendee's
     * order item is shared with others, it is split so only this attendee moves.
     * Pass '__keep__' for a dimension that should stay unchanged.
     */
    public function changeDayOrSession(Attendee $attendee, int|string|null $dayId, int|string|null $sessionId): void
    {
        $item = $attendee->ticketOrderItem;
        $ticket = $item->ticket()->with(['validDays', 'sessions'])->first();

        $targetDayId = $dayId === '__keep__' ? $item->selected_event_day_id : ($dayId === null ? null : (int) $dayId);
        $targetSessionId = $sessionId === '__keep__' ? $item->ticket_session_id : ($sessionId === null ? null : (int) $sessionId);

        if ($targetDayId !== null) {
            abort_unless($ticket->validDays->contains('id', $targetDayId), 422, 'That day is not valid for this ticket.');
        }

        if ($targetSessionId !== null) {
            $session = $ticket->sessions->firstWhere('id', $targetSessionId);
            abort_unless($session, 422, 'That session is not valid for this ticket.');

            if ($targetSessionId !== $item->ticket_session_id) {
                $left = $this->purchases->availableSessionCapacity($session);
                abort_if($left !== null && $left < 1, 422, 'The selected session is full.');
            }
        }

        $noChange = $targetDayId === $item->selected_event_day_id && $targetSessionId === $item->ticket_session_id;
        if ($noChange) {
            return;
        }

        $fromSessionId = $item->ticket_session_id;

        if ($item->attendees()->count() <= 1) {
            $item->update([
                'selected_event_day_id' => $targetDayId,
                'ticket_session_id' => $targetSessionId,
            ]);
        } else {
            $newItem = $item->ticketOrder->items()->create([
                'ticket_id' => $item->ticket_id,
                'ticket_session_id' => $targetSessionId,
                'selected_event_day_id' => $targetDayId,
                'quantity' => 1,
                'unit_price' => $item->unit_price,
                'phase_label' => $item->phase_label,
                'subtotal' => $item->unit_price,
            ]);

            $item->decrement('quantity');
            $item->update(['subtotal' => $item->unit_price * $item->quantity]);
            $attendee->update(['ticket_order_item_id' => $newItem->id]);
        }

        if ($fromSessionId !== $targetSessionId) {
            if ($fromSessionId) {
                TicketSession::whereKey($fromSessionId)->where('booked_count', '>', 0)->decrement('booked_count');
            }
            if ($targetSessionId) {
                TicketSession::whereKey($targetSessionId)->increment('booked_count');
            }
        }
    }

    public function setCheckIn(Attendee $attendee, bool $checkedIn, int $staffId, int $eventId): void
    {
        if ($checkedIn && $attendee->checked_in_at === null) {
            $attendee->update([
                'checked_in_at' => now(),
                'checked_in_by' => $staffId,
                'checkin_event_id' => $eventId,
            ]);
        } elseif (! $checkedIn && $attendee->checked_in_at !== null) {
            $attendee->update([
                'checked_in_at' => null,
                'checked_in_by' => null,
                'checkin_event_id' => null,
            ]);
        }
    }
}
