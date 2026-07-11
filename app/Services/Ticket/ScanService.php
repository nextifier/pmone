<?php

namespace App\Services\Ticket;

use App\Enums\Ticketing\ScanAction;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\ScanLog;
use App\Support\QrToken;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Redeem / check-in engine. Validates a scanned attendee (order confirmed,
 * day/session timing, cross-day + cross-event scan rules), marks check-in
 * once (first-wins), and records every action in the append-only ScanLog.
 */
class ScanService
{
    /**
     * @return array<string, mixed>
     */
    public function checkIn(string $qrToken, Event $scanEvent, int $staffId, string $idempotencyKey, ScanAction $action = ScanAction::CheckIn, ?string $clientScannedAt = null): array
    {
        $attendee = $this->resolveAttendee($qrToken);

        if (! $attendee) {
            return ['result' => 'invalid', 'reason' => 'ticket_not_found'];
        }

        if ($attendee->cancelled_at !== null) {
            return ['result' => 'invalid', 'reason' => 'ticket_cancelled', 'attendee' => $this->present($attendee)];
        }

        $order = $attendee->ticketOrderItem?->ticketOrder;

        if (! $order || ! $order->isConfirmed()) {
            return ['result' => 'invalid', 'reason' => 'order_not_confirmed', 'attendee' => $this->present($attendee)];
        }

        $ticketEvent = $attendee->ticket?->event;

        // Cross-event redeem requires an allow_cross_scan conjunction link.
        if ($ticketEvent && $ticketEvent->id !== $scanEvent->id && ! $this->crossScanAllowed($ticketEvent->id, $scanEvent->id)) {
            return ['result' => 'invalid', 'reason' => 'wrong_event', 'attendee' => $this->present($attendee)];
        }

        $timing = $this->validateTiming($attendee, $scanEvent);
        $scannedAt = $this->resolveScannedAt($clientScannedAt);

        // Idempotent ledger entry — repeated offline pushes of the same client
        // UUID collapse to one row.
        ScanLog::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'attendee_id' => $attendee->id,
                'action' => $action,
                'event_id' => $scanEvent->id,
                'staff_id' => $staffId,
                'scanned_at' => $scannedAt,
                'meta' => ['warning' => $timing['warning'] ?? null],
            ],
        );

        if ($action === ScanAction::Reprint || $action === ScanAction::Reissue) {
            if ($action === ScanAction::Reissue) {
                // A re-issue invalidates the lost/stolen badge by rotating its QR
                // token, so the old e-ticket / badge can no longer be scanned.
                $attendee->forceFill(['qr_token' => (string) Str::ulid()])->save();
            }

            $attendee->increment('reprint_count');

            return ['result' => 'reprinted', 'action' => $action->value, 'attendee' => $this->present($attendee->fresh(['ticket', 'ticket.validDays', 'ticketOrderItem.selectedEventDay']))];
        }

        $alreadyCheckedIn = $attendee->checked_in_at !== null;

        if (! $alreadyCheckedIn) {
            // First-wins: only the first scan whose row still has a null
            // checked_in_at sets the state. Concurrent devices lose the race.
            Attendee::query()
                ->whereKey($attendee->id)
                ->whereNull('checked_in_at')
                ->update([
                    'checked_in_at' => $scannedAt,
                    'checked_in_by' => $staffId,
                    'checkin_event_id' => $scanEvent->id,
                ]);
        }

        return [
            'result' => $alreadyCheckedIn ? 'already_checked_in' : 'checked_in',
            'warning' => $timing['warning'] ?? null,
            'attendee' => $this->present($attendee->fresh(['ticket', 'ticket.validDays', 'ticketOrderItem.selectedEventDay'])),
        ];
    }

    /**
     * Search attendees of an event (and cross-scan partners) by name / email /
     * phone / order number for manual check-in.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(Event $scanEvent, string $query): array
    {
        $eventIds = $this->scannableEventIds($scanEvent);
        $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return Attendee::query()
            ->whereHas('ticket', fn ($q) => $q->whereIn('event_id', $eventIds))
            ->whereHas('ticketOrderItem.ticketOrder', fn ($q) => $q->where('status', 'confirmed'))
            ->whereNull('cancelled_at')
            ->where(function ($q) use ($like, $query) {
                $q->where('name', $like, "%{$query}%")
                    ->orWhere('email', $like, "%{$query}%")
                    ->orWhere('phone', $like, "%{$query}%")
                    ->orWhereHas('ticketOrderItem.ticketOrder', fn ($o) => $o->where('order_number', $like, "%{$query}%"));
            })
            ->with(['ticket.validDays'])
            ->limit(25)
            ->get()
            ->map(fn (Attendee $a) => $this->present($a))
            ->all();
    }

    /**
     * Offline manifest: every confirmed-order attendee scannable at this event
     * (own + cross-scan partners), trimmed to what the scanner needs.
     *
     * @return array<int, array<string, mixed>>
     */
    public function manifest(Event $scanEvent): array
    {
        $eventIds = $this->scannableEventIds($scanEvent);

        return Attendee::query()
            ->whereHas('ticket', fn ($q) => $q->whereIn('event_id', $eventIds))
            ->whereHas('ticketOrderItem.ticketOrder', fn ($q) => $q->where('status', 'confirmed'))
            ->whereNull('cancelled_at')
            ->with(['ticket.validDays', 'ticketOrderItem:id,selected_event_day_id'])
            // Stream in chunks (eager-loaded per chunk) instead of hydrating every
            // confirmed attendee at once - keeps peak memory bounded on large
            // events. The full mapped manifest is still returned (the offline
            // scanner needs every token to validate without a network).
            ->lazy()
            ->map(fn (Attendee $a) => [
                'qr_token' => $a->qr_token,
                'name' => $a->name,
                'kind' => $a->ticket?->kind?->value,
                'tier' => $a->ticket?->tier,
                'valid_day_ids' => $a->ticketOrderItem?->selected_event_day_id
                    ? [$a->ticketOrderItem->selected_event_day_id]
                    : ($a->ticket?->validDays->pluck('id')->all() ?? []),
                'event_id' => $a->ticket?->event_id,
                'checked_in_at' => $a->checked_in_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Pull check-ins recorded since a cursor (for device-to-device sync).
     *
     * @return array<int, array<string, mixed>>
     */
    public function checkInsSince(Event $scanEvent, ?string $cursor): array
    {
        $eventIds = $this->scannableEventIds($scanEvent);

        return ScanLog::query()
            ->where('action', ScanAction::CheckIn->value)
            ->whereIn('event_id', $eventIds)
            ->when($cursor, fn ($q) => $q->where('scanned_at', '>', Carbon::parse($cursor)))
            ->orderBy('scanned_at')
            ->with('attendee:id,ulid,qr_token')
            ->limit(2000)
            ->get()
            ->map(fn (ScanLog $log) => [
                'qr_token' => $log->attendee?->qr_token,
                'scanned_at' => $log->scanned_at?->toIso8601String(),
                'idempotency_key' => $log->idempotency_key,
            ])
            ->all();
    }

    /**
     * Bound an offline-synced scan's client-reported timestamp to +/-24h of
     * the server clock, so a wildly wrong device time can't corrupt
     * attendance timing or day-validity reports. Falls back to server time
     * when the client didn't send one, sent something unparsable, or sent a
     * time outside the bound. Online single check-ins never pass a client
     * time, so they always get exact server time.
     */
    protected function resolveScannedAt(?string $clientScannedAt): Carbon
    {
        if (! $clientScannedAt) {
            return now();
        }

        try {
            $parsed = Carbon::parse($clientScannedAt);
        } catch (\Throwable) {
            return now();
        }

        return $parsed->diffInHours(now(), true) > 24 ? now() : $parsed;
    }

    protected function resolveAttendee(string $qrToken): ?Attendee
    {
        return Attendee::query()
            ->where('qr_token', QrToken::normalize($qrToken))
            ->with(['ticket.event', 'ticket.validDays', 'ticketOrderItem.ticketOrder', 'ticketOrderItem.selectedEventDay'])
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateTiming(Attendee $attendee, Event $scanEvent): array
    {
        $ticket = $attendee->ticket;

        if (! $ticket) {
            return ['valid' => false, 'warning' => 'unknown_ticket'];
        }

        // Add-ons: validity is the session window (or always, when sessionless).
        if ($ticket->isAddOn()) {
            return ['valid' => true, 'warning' => null];
        }

        // Entry: valid on its valid_days. Empty valid_days = valid every day.
        $validDayIds = $ticket->validDays->pluck('id');

        // A buyer-chosen day (Day Pass) narrows validity to just that one day.
        $selectedDayId = $attendee->ticketOrderItem?->selected_event_day_id;
        if ($selectedDayId) {
            $validDayIds = collect([$selectedDayId]);
        }

        if ($validDayIds->isEmpty()) {
            return ['valid' => true, 'warning' => null];
        }

        $today = Carbon::now($scanEvent->timezone ?? config('app.timezone'))->toDateString();
        $todayDay = $ticket->event?->eventDays->firstWhere(fn ($d) => $d->date?->toDateString() === $today)
            ?? Event::find($ticket->event_id)?->eventDays()->whereDate('date', $today)->first();

        if ($todayDay && $validDayIds->contains($todayDay->id)) {
            return ['valid' => true, 'warning' => null];
        }

        // Outside valid days: a soft warning the scanner shows; allow_cross_day
        // suppresses it. Never a hard block — staff make the final call.
        return [
            'valid' => true,
            'warning' => $scanEvent->allow_cross_day ? null : 'cross_day',
        ];
    }

    protected function crossScanAllowed(int $ticketEventId, int $scanEventId): bool
    {
        return DB::table('event_conjunctions')
            ->where('event_id', $ticketEventId)
            ->where('conjunction_event_id', $scanEventId)
            ->where('allow_cross_scan', true)
            ->exists();
    }

    /**
     * Events scannable at this gate: the event itself plus any conjunction
     * partner flagged allow_cross_scan.
     *
     * @return array<int, int>
     */
    protected function scannableEventIds(Event $scanEvent): array
    {
        $partners = DB::table('event_conjunctions')
            ->where('conjunction_event_id', $scanEvent->id)
            ->where('allow_cross_scan', true)
            ->pluck('event_id')
            ->all();

        return array_values(array_unique(array_merge([$scanEvent->id], $partners)));
    }

    /**
     * @return array<string, mixed>
     */
    protected function present(Attendee $attendee): array
    {
        $ticket = $attendee->ticket;
        $selectedDay = $attendee->ticketOrderItem?->selectedEventDay;

        return [
            'ulid' => $attendee->ulid,
            'qr_token' => $attendee->qr_token,
            'name' => $attendee->name,
            'email' => $attendee->email,
            'kind' => $ticket?->kind?->value,
            'tier' => $ticket?->tier,
            'print_on_redeem' => (bool) $ticket?->print_on_redeem,
            'title' => $ticket?->getTranslation('title', app()->getLocale(), false),
            'valid_day_ids' => $selectedDay
                ? [$selectedDay->id]
                : ($ticket?->relationLoaded('validDays') ? $ticket->validDays->pluck('id')->all() : []),
            'selected_day' => $selectedDay ? [
                'day_number' => $selectedDay->day_number,
                'label' => $selectedDay->label,
                'date' => $selectedDay->date?->toDateString(),
            ] : null,
            'event_id' => $ticket?->event_id,
            'checked_in_at' => $attendee->checked_in_at?->toIso8601String(),
            'reprint_count' => $attendee->reprint_count,
        ];
    }
}
