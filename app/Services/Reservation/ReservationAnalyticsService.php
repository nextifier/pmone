<?php

namespace App\Services\Reservation;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Models\Event;
use App\Models\HotelEventAllotment;
use App\Models\Reservation;
use App\Services\Ticket\AttendeeAnalyticsService;
use Illuminate\Support\Collection;

/**
 * Aggregates hotel reservation data for an event into a decision-maker friendly
 * analytics payload. Mirrors {@see AttendeeAnalyticsService}:
 * computed on-demand, categorical breakdowns + time-series grouped in PHP so the
 * queries stay portable across PostgreSQL (prod) and the SQLite test database.
 */
class ReservationAnalyticsService
{
    /**
     * Lightweight KPI block for the summary strip above the reservations table.
     *
     * @return array<string, mixed>
     */
    public function summary(Event $event): array
    {
        return $this->buildSummary($event, $this->reservations($event));
    }

    /**
     * Full analytics payload for the detail dashboard.
     *
     * @return array<string, mixed>
     */
    public function detail(Event $event): array
    {
        $reservations = $this->reservations($event);

        return [
            'summary' => $this->buildSummary($event, $reservations),
            'bookings_over_time' => $this->bookingsOverTime($reservations),
            'by_status' => $this->byStatus($reservations),
            'by_hotel' => $this->byHotel($reservations),
            'by_room_type' => $this->byRoomType($reservations),
            'payment_channels' => $this->paymentChannels($reservations),
            'by_source' => $this->bySource($reservations),
            'stay_lengths' => $this->stayLengths($reservations),
            'by_nationality' => $this->byNationality($reservations),
            'top_guests' => $this->topGuests($reservations),
        ];
    }

    /**
     * @return Collection<int, Reservation>
     */
    private function reservations(Event $event): Collection
    {
        return Reservation::query()
            ->where('event_id', $event->id)
            ->with([
                'items:id,reservation_id,room_type_id,nights,qty,subtotal',
                'items.roomType:id,name',
                'hotel:id,name',
            ])
            ->get([
                'id', 'status', 'total_amount', 'payment_channel', 'payment_method',
                'source', 'guest_name', 'guest_email', 'guest_nationality',
                'hotel_id', 'created_at', 'paid_at', 'payment_expires_at',
            ]);
    }

    /**
     * @param  Collection<int, Reservation>  $reservations
     * @return array<string, mixed>
     */
    private function buildSummary(Event $event, Collection $reservations): array
    {
        $paid = $reservations->filter(fn (Reservation $r): bool => $r->status->isPaid());
        $revenue = (float) $paid->sum(fn (Reservation $r): float => (float) $r->total_amount);
        $roomNights = (int) $paid->sum(fn (Reservation $r): int => $this->roomNights($r));

        $totalAllotment = $this->totalAllotment($event);
        $roomsSold = (int) $reservations
            ->filter(fn (Reservation $r): bool => $this->isCommitted($r))
            ->sum(fn (Reservation $r): int => (int) $r->items->sum('qty'));

        return [
            'total_reservations' => $reservations->count(),
            'paid_reservations' => $paid->count(),
            'pending_reservations' => $reservations
                ->filter(fn (Reservation $r): bool => $r->status === ReservationStatus::PendingPayment)
                ->count(),
            'cancelled_reservations' => $reservations
                ->filter(fn (Reservation $r): bool => $r->status->isFinal())
                ->count(),
            'occupancy_rate' => $totalAllotment > 0
                ? min(100.0, round($roomsSold / $totalAllotment * 100, 1))
                : 0.0,
            'rooms_sold' => $roomsSold,
            'total_allotment' => $totalAllotment,
            'room_nights' => $roomNights,
            'total_revenue' => $revenue,
            'avg_booking_value' => $paid->count() > 0 ? round($revenue / $paid->count(), 2) : 0.0,
            'unique_guests' => $reservations
                ->map(fn (Reservation $r): string => mb_strtolower((string) $r->guest_email))
                ->filter()
                ->unique()
                ->count(),
            'currency' => 'IDR',
        ];
    }

    /**
     * @param  Collection<int, Reservation>  $reservations
     * @return array<int, array<string, mixed>>
     */
    private function bookingsOverTime(Collection $reservations): array
    {
        $grouped = $reservations
            ->filter(fn (Reservation $r): bool => $r->created_at !== null)
            ->groupBy(fn (Reservation $r): string => $r->created_at->format('Y-m-d'))
            ->sortKeys();

        $rows = [];

        foreach ($grouped as $date => $group) {
            $paid = $group->filter(fn (Reservation $r): bool => $r->status->isPaid());

            $rows[] = [
                'date' => $date,
                'reservations' => $group->count(),
                'paid' => $paid->count(),
                'revenue' => (float) $paid->sum(fn (Reservation $r): float => (float) $r->total_amount),
                'room_nights' => (int) $paid->sum(fn (Reservation $r): int => $this->roomNights($r)),
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, Reservation>  $reservations
     * @return array<int, array<string, mixed>>
     */
    private function byStatus(Collection $reservations): array
    {
        return $reservations
            ->groupBy(fn (Reservation $r): string => $r->status->value)
            ->map(fn (Collection $group, string $status): array => [
                'status' => $status,
                'label' => ReservationStatus::from($status)->label(),
                'count' => $group->count(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Reservation>  $reservations
     * @return array<int, array<string, mixed>>
     */
    private function byHotel(Collection $reservations): array
    {
        return $reservations
            ->filter(fn (Reservation $r): bool => $r->status->isPaid())
            ->groupBy('hotel_id')
            ->map(fn (Collection $group): array => [
                'hotel_id' => (int) $group->first()->hotel_id,
                'name' => $group->first()->hotel?->name ?? 'Unknown hotel',
                'reservations' => $group->count(),
                'room_nights' => (int) $group->sum(fn (Reservation $r): int => $this->roomNights($r)),
                'revenue' => (float) $group->sum(fn (Reservation $r): float => (float) $r->total_amount),
            ])
            ->sortByDesc('revenue')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Reservation>  $reservations
     * @return array<int, array<string, mixed>>
     */
    private function byRoomType(Collection $reservations): array
    {
        $rooms = [];

        foreach ($reservations as $reservation) {
            if (! $reservation->status->isPaid()) {
                continue;
            }
            foreach ($reservation->items as $item) {
                $id = $item->room_type_id;
                if (! isset($rooms[$id])) {
                    $rooms[$id] = [
                        'room_type_id' => (int) $id,
                        'name' => $item->roomType?->name ?? 'Unknown room',
                        'rooms' => 0,
                        'nights' => 0,
                        'revenue' => 0.0,
                    ];
                }
                $rooms[$id]['rooms'] += (int) $item->qty;
                $rooms[$id]['nights'] += (int) $item->nights * (int) $item->qty;
                $rooms[$id]['revenue'] += (float) $item->subtotal;
            }
        }

        return collect($rooms)->sortByDesc('nights')->take(10)->values()->all();
    }

    /**
     * @param  Collection<int, Reservation>  $reservations
     * @return array<int, array<string, mixed>>
     */
    private function paymentChannels(Collection $reservations): array
    {
        return $reservations
            ->filter(fn (Reservation $r): bool => $r->status->isPaid())
            ->groupBy(fn (Reservation $r): string => $this->channelLabel($r))
            ->map(fn (Collection $group, string $channel): array => [
                'channel' => $channel,
                'reservations' => $group->count(),
                'revenue' => (float) $group->sum(fn (Reservation $r): float => (float) $r->total_amount),
            ])
            ->sortByDesc('revenue')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Reservation>  $reservations
     * @return array<int, array<string, mixed>>
     */
    private function bySource(Collection $reservations): array
    {
        return $reservations
            ->groupBy(fn (Reservation $r): string => $r->source->value)
            ->map(fn (Collection $group, string $source): array => [
                'source' => $source,
                'label' => ReservationSource::from($source)->label(),
                'count' => $group->count(),
            ])
            ->values()
            ->all();
    }

    /**
     * Distribution of stay length (nights), counting each booked room item.
     *
     * @param  Collection<int, Reservation>  $reservations
     * @return array<int, array<string, mixed>>
     */
    private function stayLengths(Collection $reservations): array
    {
        $counts = [];

        foreach ($reservations as $reservation) {
            if (! $reservation->status->isPaid()) {
                continue;
            }
            foreach ($reservation->items as $item) {
                $nights = (int) $item->nights;
                if ($nights <= 0) {
                    continue;
                }
                $counts[$nights] = ($counts[$nights] ?? 0) + (int) $item->qty;
            }
        }

        ksort($counts);

        return collect($counts)
            ->map(fn (int $count, int $nights): array => ['nights' => $nights, 'count' => $count])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Reservation>  $reservations
     * @return array<int, array<string, mixed>>
     */
    private function byNationality(Collection $reservations): array
    {
        return $reservations
            ->filter(fn (Reservation $r): bool => ! empty($r->guest_nationality))
            ->groupBy(fn (Reservation $r): string => (string) $r->guest_nationality)
            ->map(fn (Collection $group, string $nationality): array => [
                'nationality' => $nationality,
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->take(10)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Reservation>  $reservations
     * @return array<int, array<string, mixed>>
     */
    private function topGuests(Collection $reservations): array
    {
        return $reservations
            ->filter(fn (Reservation $r): bool => $r->status->isPaid())
            ->groupBy(fn (Reservation $r): string => mb_strtolower($r->guest_email ?? 'unknown'))
            ->map(function (Collection $group): array {
                $first = $group->first();

                return [
                    'name' => $first->guest_name ?: ($first->guest_email ?: 'Unknown guest'),
                    'email' => $first->guest_email,
                    'reservations' => $group->count(),
                    'nights' => (int) $group->sum(fn (Reservation $r): int => $this->roomNights($r)),
                    'total_spent' => (float) $group->sum(fn (Reservation $r): float => (float) $r->total_amount),
                ];
            })
            ->sortByDesc('total_spent')
            ->take(10)
            ->values()
            ->all();
    }

    /**
     * Total room nights committed by a reservation (nights x quantity per item).
     */
    private function roomNights(Reservation $reservation): int
    {
        return (int) $reservation->items->sum(fn ($item): int => (int) $item->nights * (int) $item->qty);
    }

    /**
     * A reservation holds rooms when it is paid, or a pending payment that has
     * not yet lapsed. Mirrors the "committed" rule in
     * {@see ReservationService::checkAvailability()} so occupancy lines up with
     * availability.
     */
    private function isCommitted(Reservation $reservation): bool
    {
        if ($reservation->status->isPaid()) {
            return true;
        }

        return $reservation->status === ReservationStatus::PendingPayment
            && $reservation->payment_expires_at !== null
            && $reservation->payment_expires_at->isFuture();
    }

    /**
     * Total rooms set aside for this event: the active allotment blocks of every
     * hotel currently attached to the event. Allotments are global per hotel
     * (the event_id column was dropped), so the event scope comes from the
     * hotel_event pivot.
     */
    private function totalAllotment(Event $event): int
    {
        $hotelIds = $event->hotels()->wherePivot('is_active', true)->pluck('hotels.id');

        if ($hotelIds->isEmpty()) {
            return 0;
        }

        return (int) HotelEventAllotment::query()
            ->active()
            ->whereIn('hotel_id', $hotelIds)
            ->sum('quantity');
    }

    private function channelLabel(Reservation $reservation): string
    {
        if ((float) $reservation->total_amount <= 0.0) {
            return 'Free / Complimentary';
        }

        return $reservation->payment_channel ?: 'Other';
    }
}
