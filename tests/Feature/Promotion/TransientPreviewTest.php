<?php

use App\Models\Event;
use App\Models\Hotel;
use App\Models\HotelEvent;
use App\Models\HotelEventAllotment;
use App\Models\PromoCode;
use App\Models\PromotionRule;
use App\Models\RoomType;
use App\Services\Promotion\PromoCodeService;
use App\Services\Reservation\TransientReservationBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('preview uses allotment base_rate_override + surcharge for transient subtotal', function () {
    $event = Event::factory()->create();
    $hotel = Hotel::factory()->withEvent($event)->create([
        'tax_percentage' => 0,
        'service_charge_percentage' => 0,
    ]);
    HotelEvent::query()->updateOrCreate(
        ['hotel_id' => $hotel->id, 'event_id' => $event->id],
        ['is_active' => true]
    );
    $room = RoomType::factory()->create([
        'hotel_id' => $hotel->id,
        'base_rate' => 1_000_000,
    ]);
    HotelEventAllotment::factory()->create([
        'hotel_id' => $hotel->id,
        'room_type_id' => $room->id,
        'quantity' => 10,
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-31',
        'is_active' => true,
        'base_rate_override' => 800_000,
        'surcharge_type' => 'fixed',
        'surcharge_amount' => 50_000,
    ]);

    $reservation = app(TransientReservationBuilder::class)->build([
        'hotel_id' => $hotel->id,
        'event_id' => $event->id,
        'guest_email' => 'test@example.com',
        'items' => [[
            'room_type_id' => $room->id,
            'check_in_date' => '2026-07-10',
            'check_out_date' => '2026-07-12',
            'qty' => 1,
        ]],
    ]);

    // 2 nights × (800k override + 50k fixed surcharge) = 1.7M base subtotal
    expect((float) $reservation->subtotal_rooms)->toBe(1_700_000.0)
        ->and((float) $reservation->surcharge_amount)->toBe(100_000.0)
        ->and((int) $reservation->event_id)->toBe($event->id);
});

it('event_scoped promo applicability passes in preview when event_id is forwarded', function () {
    $event = Event::factory()->create();
    $hotel = Hotel::factory()->withEvent($event)->create([
        'tax_percentage' => 0,
        'service_charge_percentage' => 0,
    ]);
    HotelEvent::query()->updateOrCreate(
        ['hotel_id' => $hotel->id, 'event_id' => $event->id],
        ['is_active' => true]
    );
    $room = RoomType::factory()->create(['hotel_id' => $hotel->id, 'base_rate' => 1_000_000]);
    HotelEventAllotment::factory()->create([
        'hotel_id' => $hotel->id,
        'room_type_id' => $room->id,
        'quantity' => 5,
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-31',
        'is_active' => true,
    ]);

    $rule = PromotionRule::factory()->percentage(10)->create([
        'applicability' => ['events' => [$event->id]],
    ]);
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'EVENTONLY']);

    $reservation = app(TransientReservationBuilder::class)->build([
        'hotel_id' => $hotel->id,
        'event_id' => $event->id,
        'guest_email' => 'test@example.com',
        'items' => [[
            'room_type_id' => $room->id,
            'check_in_date' => '2026-07-10',
            'check_out_date' => '2026-07-11',
            'qty' => 1,
        ]],
    ]);

    $result = app(PromoCodeService::class)->validate('EVENTONLY', $reservation, 'test@example.com');

    expect($result->valid)->toBeTrue();
});

it('event_scoped promo rejects in preview when reservation event mismatches applicability', function () {
    $eventA = Event::factory()->create();
    $eventB = Event::factory()->create();
    $hotel = Hotel::factory()->withEvent($eventA)->create([
        'tax_percentage' => 0,
        'service_charge_percentage' => 0,
    ]);
    HotelEvent::query()->updateOrCreate(
        ['hotel_id' => $hotel->id, 'event_id' => $eventA->id],
        ['is_active' => true]
    );
    $room = RoomType::factory()->create(['hotel_id' => $hotel->id, 'base_rate' => 1_000_000]);
    HotelEventAllotment::factory()->create([
        'hotel_id' => $hotel->id,
        'room_type_id' => $room->id,
        'quantity' => 5,
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-31',
        'is_active' => true,
    ]);

    $rule = PromotionRule::factory()->percentage(10)->create([
        'applicability' => ['events' => [$eventB->id]],
    ]);
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'EVENTB']);

    $reservation = app(TransientReservationBuilder::class)->build([
        'hotel_id' => $hotel->id,
        'event_id' => $eventA->id,
        'guest_email' => 'test@example.com',
        'items' => [[
            'room_type_id' => $room->id,
            'check_in_date' => '2026-07-10',
            'check_out_date' => '2026-07-11',
            'qty' => 1,
        ]],
    ]);

    $result = app(PromoCodeService::class)->validate('EVENTB', $reservation, 'test@example.com');

    expect($result->valid)->toBeFalse();
});
