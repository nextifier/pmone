<?php

namespace App\Services\Ticket;

use App\Enums\Ticketing\TicketKind;
use App\Enums\Ticketing\TicketOrderStatus;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventCustomField;
use App\Models\EventDay;
use App\Models\ExhibitorLead;
use App\Models\FieldResponse;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketSession;
use App\Support\FormFieldTypes;
use Illuminate\Support\Collection;

/**
 * Aggregates attendee + ticketing data for an event into a decision-maker
 * friendly analytics payload. Computed on-demand: categorical breakdowns use
 * Eloquent aggregates, time-series are grouped in PHP so the queries stay
 * portable across PostgreSQL (prod) and the SQLite test database.
 */
class AttendeeAnalyticsService
{
    /**
     * Lightweight KPI block for the summary strip above the attendees table.
     *
     * @return array<string, mixed>
     */
    public function summary(Event $event): array
    {
        return $this->buildSummary($event, $this->attendees($event), $this->orders($event));
    }

    /**
     * Full analytics payload for the detail dashboard.
     *
     * @return array<string, mixed>
     */
    public function detail(Event $event): array
    {
        $attendees = $this->attendees($event);
        $orders = $this->orders($event);
        $days = EventDay::query()->where('event_id', $event->id)->orderBy('day_number')->get();
        $tickets = Ticket::query()->where('event_id', $event->id)->with('validDays:id')->get()->keyBy('id');

        return [
            'summary' => $this->buildSummary($event, $attendees, $orders),
            'registrations_over_time' => $this->registrationsOverTime($orders),
            'check_ins_over_time' => $this->checkInsOverTime($attendees),
            'by_ticket_type' => $this->byTicketType($attendees, $orders, $tickets),
            'by_event_day' => $this->byEventDay($attendees, $days, $tickets),
            'by_session' => $this->bySession($event, $attendees),
            'payment_channels' => $this->paymentChannels($orders),
            'order_status' => $this->orderStatus($orders),
            'top_buyers' => $this->topBuyers($orders),
            'demographics' => $event->business_matching_enabled ? $this->demographics($event) : [],
            'exhibitor_leads' => $event->business_matching_enabled ? $this->exhibitorLeads($event) : null,
        ];
    }

    /**
     * @return Collection<int, Attendee>
     */
    private function attendees(Event $event): Collection
    {
        return Attendee::query()
            ->forEvent($event->id)
            ->with([
                'ticketOrderItem:id,ticket_order_id,ticket_id,ticket_session_id,selected_event_day_id',
                'ticketOrderItem.ticketOrder:id,status',
            ])
            ->get(['id', 'ticket_id', 'ticket_order_item_id', 'checked_in_at', 'personalized_at', 'claimed_by_user_id']);
    }

    /**
     * @return Collection<int, TicketOrder>
     */
    private function orders(Event $event): Collection
    {
        return TicketOrder::query()
            ->where('event_id', $event->id)
            ->with(['items:id,ticket_order_id,ticket_id,ticket_session_id,quantity,subtotal'])
            ->get(['id', 'status', 'total', 'payment_channel', 'created_at', 'paid_at', 'buyer_name', 'buyer_email']);
    }

    /**
     * @param  Collection<int, Attendee>  $attendees
     * @param  Collection<int, TicketOrder>  $orders
     * @return array<string, mixed>
     */
    private function buildSummary(Event $event, Collection $attendees, Collection $orders): array
    {
        $total = $attendees->count();
        $checkedIn = $attendees->filter(fn (Attendee $a): bool => $a->checked_in_at !== null)->count();
        $confirmed = $orders->filter(fn (TicketOrder $o): bool => $o->status === TicketOrderStatus::Confirmed);
        $revenue = (float) $confirmed->sum(fn (TicketOrder $o): float => (float) $o->total);

        return [
            'total_attendees' => $total,
            'checked_in' => $checkedIn,
            'not_checked_in' => $total - $checkedIn,
            'check_in_rate' => $this->rate($checkedIn, $total),
            'personalized' => $attendees->filter(fn (Attendee $a): bool => $a->personalized_at !== null)->count(),
            'claimed' => $attendees->filter(fn (Attendee $a): bool => $a->claimed_by_user_id !== null)->count(),
            'total_orders' => $orders->count(),
            'confirmed_orders' => $confirmed->count(),
            'pending_orders' => $orders->filter(fn (TicketOrder $o): bool => $o->status === TicketOrderStatus::PendingPayment)->count(),
            'tickets_sold' => $attendees->filter(fn (Attendee $a): bool => $this->isConfirmedAttendee($a))->count(),
            'total_revenue' => $revenue,
            'avg_order_value' => $confirmed->count() > 0 ? round($revenue / $confirmed->count(), 2) : 0.0,
            'currency' => $this->currency($event),
        ];
    }

    /**
     * @param  Collection<int, TicketOrder>  $orders
     * @return array<int, array<string, mixed>>
     */
    private function registrationsOverTime(Collection $orders): array
    {
        $grouped = $orders
            ->filter(fn (TicketOrder $o): bool => $o->created_at !== null)
            ->groupBy(fn (TicketOrder $o): string => $o->created_at->format('Y-m-d'))
            ->sortKeys();

        $cumulativeTickets = 0;
        $rows = [];

        foreach ($grouped as $date => $group) {
            $confirmed = $group->filter(fn (TicketOrder $o): bool => $o->status === TicketOrderStatus::Confirmed);
            $tickets = (int) $confirmed->sum(fn (TicketOrder $o): int => (int) $o->items->sum('quantity'));
            $cumulativeTickets += $tickets;

            $rows[] = [
                'date' => $date,
                'orders' => $group->count(),
                'tickets' => $tickets,
                'revenue' => (float) $confirmed->sum(fn (TicketOrder $o): float => (float) $o->total),
                'cumulative_tickets' => $cumulativeTickets,
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, Attendee>  $attendees
     * @return array<int, array<string, mixed>>
     */
    private function checkInsOverTime(Collection $attendees): array
    {
        return $attendees
            ->filter(fn (Attendee $a): bool => $a->checked_in_at !== null)
            ->groupBy(fn (Attendee $a): string => $a->checked_in_at->format('Y-m-d H:00'))
            ->sortKeys()
            ->map(fn (Collection $group, string $slot): array => [
                'slot' => $slot,
                'count' => $group->count(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Attendee>  $attendees
     * @param  Collection<int, TicketOrder>  $orders
     * @param  Collection<int, Ticket>  $tickets
     * @return array<int, array<string, mixed>>
     */
    private function byTicketType(Collection $attendees, Collection $orders, Collection $tickets): array
    {
        $revenueByTicket = [];
        foreach ($orders as $order) {
            if ($order->status !== TicketOrderStatus::Confirmed) {
                continue;
            }
            foreach ($order->items as $item) {
                $revenueByTicket[$item->ticket_id] = ($revenueByTicket[$item->ticket_id] ?? 0) + (float) $item->subtotal;
            }
        }

        return $attendees
            ->groupBy('ticket_id')
            ->map(function (Collection $group, $ticketId) use ($tickets, $revenueByTicket): array {
                $checkedIn = $group->filter(fn (Attendee $a): bool => $a->checked_in_at !== null)->count();
                $ticket = $tickets->get($ticketId);

                return [
                    'ticket_id' => (int) $ticketId,
                    'title' => $ticket ? $this->ticketTitle($ticket) : 'Unknown ticket',
                    'tier' => $ticket?->tier,
                    'sold' => $group->filter(fn (Attendee $a): bool => $this->isConfirmedAttendee($a))->count(),
                    'issued' => $group->count(),
                    'checked_in' => $checkedIn,
                    'check_in_rate' => $this->rate($checkedIn, $group->count()),
                    'revenue' => (float) ($revenueByTicket[$ticketId] ?? 0),
                ];
            })
            ->sortByDesc('issued')
            ->values()
            ->all();
    }

    /**
     * Checked-in entry attendees counted on each day they are valid for: the
     * explicitly selected day, else the ticket's valid days (all days when a
     * ticket carries no day restriction). Add-ons are excluded - they belong to
     * a session, not a day. Reacts to check-ins regardless of the calendar date.
     *
     * @param  Collection<int, Attendee>  $attendees
     * @param  Collection<int, EventDay>  $days
     * @param  Collection<int, Ticket>  $tickets
     * @return array<int, array<string, mixed>>
     */
    private function byEventDay(Collection $attendees, Collection $days, Collection $tickets): array
    {
        $allDayIds = $days->pluck('id')->all();
        $counts = [];

        foreach ($attendees as $attendee) {
            if ($attendee->checked_in_at === null) {
                continue;
            }

            $ticket = $tickets->get($attendee->ticket_id);
            if ($ticket && $ticket->kind !== TicketKind::Entry) {
                continue;
            }

            $selected = $attendee->ticketOrderItem?->selected_event_day_id;
            if ($selected) {
                $dayIds = [(int) $selected];
            } else {
                $valid = $ticket ? $ticket->validDays->pluck('id')->all() : [];
                $dayIds = $valid !== [] ? $valid : $allDayIds;
            }

            foreach ($dayIds as $id) {
                $counts[$id] = ($counts[$id] ?? 0) + 1;
            }
        }

        return $days->map(fn (EventDay $day): array => [
            'day_number' => $day->day_number,
            'date' => $day->date?->format('Y-m-d'),
            'label' => $this->dayLabel($day),
            'checked_in' => (int) ($counts[$day->id] ?? 0),
        ])->all();
    }

    /**
     * @param  Collection<int, Attendee>  $attendees
     * @return array<int, array<string, mixed>>
     */
    private function bySession(Event $event, Collection $attendees): array
    {
        $sessions = TicketSession::query()
            ->whereHas('ticket', fn ($q) => $q->where('event_id', $event->id))
            ->where('is_active', true)
            ->orderBy('order_column')
            ->get(['id', 'label', 'capacity', 'booked_count']);

        if ($sessions->isEmpty()) {
            return [];
        }

        $checkedInBySession = $attendees
            ->filter(fn (Attendee $a): bool => $a->checked_in_at !== null && $a->ticketOrderItem?->ticket_session_id !== null)
            ->groupBy(fn (Attendee $a): int => (int) $a->ticketOrderItem->ticket_session_id)
            ->map(fn (Collection $group): int => $group->count());

        return $sessions->map(fn (TicketSession $session): array => [
            'session_id' => $session->id,
            'label' => $session->label,
            'capacity' => $session->capacity,
            'booked' => $session->booked_count,
            'checked_in' => (int) ($checkedInBySession[$session->id] ?? 0),
        ])->all();
    }

    /**
     * @param  Collection<int, TicketOrder>  $orders
     * @return array<int, array<string, mixed>>
     */
    private function paymentChannels(Collection $orders): array
    {
        return $orders
            ->filter(fn (TicketOrder $o): bool => $o->status === TicketOrderStatus::Confirmed)
            ->groupBy(fn (TicketOrder $o): string => $this->channelLabel($o))
            ->map(fn (Collection $group, string $channel): array => [
                'channel' => $channel,
                'orders' => $group->count(),
                'revenue' => (float) $group->sum(fn (TicketOrder $o): float => (float) $o->total),
            ])
            ->sortByDesc('revenue')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, TicketOrder>  $orders
     * @return array<int, array<string, mixed>>
     */
    private function orderStatus(Collection $orders): array
    {
        return $orders
            ->groupBy(fn (TicketOrder $o): string => $o->status->value)
            ->map(fn (Collection $group, string $status): array => [
                'status' => $status,
                'label' => TicketOrderStatus::from($status)->label(),
                'color' => TicketOrderStatus::from($status)->color(),
                'count' => $group->count(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, TicketOrder>  $orders
     * @return array<int, array<string, mixed>>
     */
    private function topBuyers(Collection $orders): array
    {
        return $orders
            ->filter(fn (TicketOrder $o): bool => $o->status === TicketOrderStatus::Confirmed)
            ->groupBy(fn (TicketOrder $o): string => mb_strtolower($o->buyer_email ?? 'unknown'))
            ->map(function (Collection $group): array {
                $first = $group->first();

                return [
                    'name' => $first->buyer_name ?: ($first->buyer_email ?: 'Unknown buyer'),
                    'email' => $first->buyer_email,
                    'orders' => $group->count(),
                    'tickets' => (int) $group->sum(fn (TicketOrder $o): int => (int) $o->items->sum('quantity')),
                    'total_spent' => (float) $group->sum(fn (TicketOrder $o): float => (float) $o->total),
                ];
            })
            ->sortByDesc('total_spent')
            ->take(10)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function demographics(Event $event): array
    {
        $fields = EventCustomField::query()
            ->where('event_id', $event->id)
            ->where('is_active', true)
            ->orderBy('order_column')
            ->get()
            ->filter(fn (EventCustomField $field): bool => FormFieldTypes::analyticsKind($field->type) === 'options');

        if ($fields->isEmpty()) {
            return [];
        }

        $responses = FieldResponse::query()
            ->whereIn('event_custom_field_id', $fields->pluck('id'))
            ->get(['event_custom_field_id', 'value'])
            ->groupBy('event_custom_field_id');

        return $fields
            ->map(function (EventCustomField $field) use ($responses): array {
                $optionLabels = $this->optionLabelMap($field);
                $counts = [];

                foreach ($responses->get($field->id, collect()) as $response) {
                    foreach ($this->normalizeValues($response->value) as $value) {
                        $label = $optionLabels[$value] ?? $value;
                        $counts[$label] = ($counts[$label] ?? 0) + 1;
                    }
                }

                arsort($counts);
                $breakdown = [];
                foreach (array_slice($counts, 0, 15, true) as $label => $count) {
                    $breakdown[] = ['value' => (string) $label, 'count' => $count];
                }

                return [
                    'field_id' => $field->id,
                    'label' => $this->fieldLabel($field),
                    'type' => $field->type,
                    'total_responses' => array_sum($counts),
                    'breakdown' => $breakdown,
                ];
            })
            ->filter(fn (array $field): bool => $field['total_responses'] > 0)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function exhibitorLeads(Event $event): array
    {
        $byBrand = ExhibitorLead::query()
            ->where('event_id', $event->id)
            ->with('brand:id,name')
            ->get(['id', 'brand_id'])
            ->groupBy('brand_id')
            ->map(fn (Collection $group): array => [
                'brand_id' => (int) $group->first()->brand_id,
                'name' => $group->first()->brand?->name ?? 'Unknown exhibitor',
                'leads' => $group->count(),
            ])
            ->sortByDesc('leads')
            ->take(10)
            ->values()
            ->all();

        return [
            'total' => ExhibitorLead::query()->where('event_id', $event->id)->count(),
            'by_brand' => $byBrand,
        ];
    }

    private function isConfirmedAttendee(Attendee $attendee): bool
    {
        return $attendee->ticketOrderItem?->ticketOrder?->status === TicketOrderStatus::Confirmed;
    }

    private function rate(int $part, int $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : 0.0;
    }

    private function currency(Event $event): string
    {
        return Ticket::query()->where('event_id', $event->id)->value('currency') ?? 'IDR';
    }

    private function channelLabel(TicketOrder $order): string
    {
        if ((float) $order->total <= 0.0) {
            return 'Free / Complimentary';
        }

        return $order->payment_channel ?: 'Other';
    }

    private function ticketTitle(Ticket $ticket): string
    {
        return $this->translatable($ticket->title, 'Ticket');
    }

    private function dayLabel(EventDay $day): string
    {
        return $this->translatable($day->label, 'Day '.$day->day_number);
    }

    private function fieldLabel(EventCustomField $field): string
    {
        return $this->translatable($field->label, 'Field');
    }

    private function translatable(mixed $value, string $fallback): string
    {
        if (is_array($value)) {
            $value = $value[app()->getLocale()] ?? (reset($value) ?: null);
        }

        return (string) ($value ?: $fallback);
    }

    /**
     * @return array<int, string>
     */
    private function normalizeValues(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        return collect(is_array($value) ? $value : [$value])
            ->flatten()
            ->filter(fn ($v): bool => is_scalar($v) && $v !== '')
            ->map(fn ($v): string => (string) $v)
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function optionLabelMap(EventCustomField $field): array
    {
        $map = [];

        foreach ($field->options ?? [] as $option) {
            if (! is_array($option) || ! isset($option['value'])) {
                continue;
            }

            $map[(string) $option['value']] = $this->translatable($option['label'] ?? $option['value'], (string) $option['value']);
        }

        return $map;
    }
}
