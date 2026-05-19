<?php

use App\Models\Event;
use App\Models\Hotel;
use App\Models\HotelEvent;
use App\Models\HotelEventAllotment;
use App\Models\HotelTransferOption;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\RoomType;

/**
 * Spin up a project + event + hotel + room + allotment + active Xendit gateway
 * mirroring the production prerequisite stated in PROMOTION_QA_PROMPT.md:
 * payment_gateways.is_active=true and events.hotel_reservation_enabled=true.
 *
 * @return array{project: Project, event: Event, hotel: Hotel, room: RoomType, allotment: HotelEventAllotment}
 */
function qaScenario(array $overrides = []): array
{
    $project = Project::factory()->create();

    ProjectPaymentGateway::factory()->create([
        'project_id' => $project->id,
        'provider' => 'xendit',
        'mode' => 'test',
        'is_active' => true,
    ]);

    $event = Event::factory()->create([
        'project_id' => $project->id,
        'hotel_reservation_enabled' => true,
        'is_active' => true,
    ]);

    $hotel = Hotel::factory()->withEvent($event)->create(array_merge([
        'tax_percentage' => 11,
        'service_charge_percentage' => 0,
        'is_active' => true,
    ], $overrides['hotel'] ?? []));

    HotelEvent::query()->updateOrCreate(
        ['hotel_id' => $hotel->id, 'event_id' => $event->id],
        ['is_active' => true]
    );

    $room = RoomType::factory()->create(array_merge([
        'hotel_id' => $hotel->id,
        'base_rate' => 1_000_000,
        'is_active' => true,
    ], $overrides['room'] ?? []));

    $allotment = HotelEventAllotment::factory()->create(array_merge([
        'hotel_id' => $hotel->id,
        'room_type_id' => $room->id,
        'quantity' => 10,
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-31',
        'is_active' => true,
    ], $overrides['allotment'] ?? []));

    return compact('project', 'event', 'hotel', 'room', 'allotment');
}

function qaTransferOption(Hotel $hotel, float $price = 250_000): HotelTransferOption
{
    return HotelTransferOption::factory()->create([
        'hotel_id' => $hotel->id,
        'price' => $price,
        'is_active' => true,
    ]);
}

/**
 * Persist a reservation with given items so adjustments can be applied.
 * Uses minimal in-test creation (skips ReservationService::createReservation
 * to avoid Xendit invoice side-effects). Tax 0, SC 0 unless overridden.
 *
 * @param  array<int, array{room_type_id: int, rate: float, qty?: int, nights?: int}>  $itemSpecs
 */
function qaReservation(array $scenario, array $itemSpecs, array $overrides = []): Reservation
{
    $subtotalRooms = 0.0;
    foreach ($itemSpecs as $spec) {
        $qty = $spec['qty'] ?? 1;
        $nights = $spec['nights'] ?? 1;
        $subtotalRooms += $spec['rate'] * $nights * $qty;
    }

    $reservation = Reservation::create(array_merge([
        'reservation_number' => 'HTL-QA-'.uniqid(),
        'event_id' => $scenario['event']->id,
        'hotel_id' => $scenario['hotel']->id,
        'guest_name' => 'QA Guest',
        'guest_email' => 'qa@example.com',
        'guest_phone' => '0812',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '1234567890',
        'subtotal_rooms' => $subtotalRooms,
        'subtotal_transfer' => 0,
        'surcharge_amount' => 0,
        'penalty_amount' => 0,
        'tax_amount' => 0,
        'service_charge_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 0,
        'magic_link_token' => bin2hex(random_bytes(32)),
        'magic_link_expires_at' => now()->addDays(90),
    ], $overrides));

    foreach ($itemSpecs as $spec) {
        $qty = $spec['qty'] ?? 1;
        $nights = $spec['nights'] ?? 1;
        ReservationItem::factory()->create([
            'reservation_id' => $reservation->id,
            'room_type_id' => $spec['room_type_id'],
            'rate_per_night' => $spec['rate'],
            'nights' => $nights,
            'qty' => $qty,
            'subtotal' => $spec['rate'] * $nights * $qty,
            'check_in_date' => $spec['check_in'] ?? '2026-07-10',
            'check_out_date' => $spec['check_out'] ?? '2026-07-'.(10 + $nights),
        ]);
    }

    return $reservation->fresh(['items', 'transfers', 'hotel']);
}
