<?php

namespace App\Services\Ticket;

use App\Models\Attendee;
use App\Models\Brand;
use App\Models\Event;
use App\Models\ExhibitorLead;
use App\Support\QrToken;
use Illuminate\Support\Facades\DB;

/**
 * Captures and reports exhibitor leads. A lead is a snapshot of an attendee at
 * scan time; one per (brand, attendee) however many times scanned. Cross-event
 * scanning follows the same allow_cross_scan conjunction rule as redemption.
 */
class ExhibitorLeadService
{
    /**
     * @return array<string, mixed>
     */
    public function capture(Brand $brand, Event $scanEvent, string $qrToken, int $userId): array
    {
        $attendee = Attendee::query()
            ->where('qr_token', QrToken::normalize($qrToken))
            ->with(['ticket.event', 'ticketOrderItem.ticketOrder'])
            ->first();

        if (! $attendee) {
            return ['result' => 'invalid', 'reason' => 'ticket_not_found'];
        }

        $order = $attendee->ticketOrderItem?->ticketOrder;
        if (! $order || ! $order->isConfirmed()) {
            return ['result' => 'invalid', 'reason' => 'order_not_confirmed'];
        }

        $ticketEventId = $attendee->ticket?->event_id;
        if ($ticketEventId && $ticketEventId !== $scanEvent->id && ! $this->crossScanAllowed($ticketEventId, $scanEvent->id)) {
            return ['result' => 'invalid', 'reason' => 'wrong_event'];
        }

        $lead = ExhibitorLead::query()->firstOrCreate(
            ['brand_id' => $brand->id, 'attendee_id' => $attendee->id],
            [
                'event_id' => $scanEvent->id,
                'scanned_by' => $userId,
                'scanned_at' => now(),
                'snapshot' => [
                    'name' => $attendee->name,
                    'email' => $attendee->email,
                    'phone' => $attendee->phone,
                    'ticket_tier' => $attendee->ticket?->tier,
                ],
            ],
        );

        return [
            'result' => $lead->wasRecentlyCreated ? 'captured' : 'already_captured',
            'lead' => [
                'id' => $lead->id,
                'name' => $attendee->name,
                'email' => $attendee->email,
                'scanned_at' => $lead->scanned_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function analytics(Brand $brand): array
    {
        $leads = ExhibitorLead::query()->where('brand_id', $brand->id);

        $perDay = (clone $leads)
            ->selectRaw('DATE(scanned_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($row) => ['day' => (string) $row->day, 'total' => (int) $row->total])
            ->all();

        return [
            'total' => (clone $leads)->count(),
            'per_day' => $perDay,
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
}
