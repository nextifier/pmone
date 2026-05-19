<?php

use App\DTOs\Promotion\PromoCodeValidation;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\HotelEventAllotment;
use App\Models\PromoCode;
use App\Models\PromotionRule;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\RoomType;
use App\Services\Promotion\PromoCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeReservationOneRoom(int $qty = 1, int $rate = 1_000_000): Reservation
{
    $event = Event::factory()->create();
    $hotel = Hotel::factory()->withEvent($event)->create([
        'tax_percentage' => 0,
        'service_charge_percentage' => 0,
    ]);
    $room = RoomType::factory()->create(['hotel_id' => $hotel->id, 'base_rate' => $rate]);
    HotelEventAllotment::factory()->create([
        'hotel_id' => $hotel->id,
        'room_type_id' => $room->id,
        'quantity' => 10,
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-31',
        'is_active' => true,
    ]);

    $reservation = Reservation::create([
        'reservation_number' => 'HTL-'.uniqid(),
        'event_id' => $event->id,
        'hotel_id' => $hotel->id,
        'guest_name' => 'Test',
        'guest_email' => 'test@example.com',
        'guest_phone' => '0812',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '1234',
        'subtotal_rooms' => $rate * $qty,
        'subtotal_transfer' => 0,
        'surcharge_amount' => 0,
        'penalty_amount' => 0,
        'tax_amount' => 0,
        'service_charge_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 0,
        'magic_link_token' => bin2hex(random_bytes(32)),
        'magic_link_expires_at' => now()->addDays(90),
    ]);

    ReservationItem::factory()->create([
        'reservation_id' => $reservation->id,
        'room_type_id' => $room->id,
        'rate_per_night' => $rate,
        'nights' => 1,
        'qty' => $qty,
        'subtotal' => $rate * $qty,
        'check_in_date' => '2026-07-10',
        'check_out_date' => '2026-07-11',
    ]);

    return $reservation->fresh(['items', 'transfers', 'hotel']);
}

it('rejects buy x get y when cart qty is below buy_qty', function () {
    $reservation = makeReservationOneRoom(qty: 1);

    $rule = PromotionRule::factory()->buyXGetY(3, 1)->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'BOGO-NQ']);

    $result = app(PromoCodeService::class)->validate('BOGO-NQ', $reservation, 'test@example.com');

    expect($result->valid)->toBeFalse()
        ->and($result->errorCode)->toBe(PromoCodeValidation::ERROR_DOES_NOT_APPLY);
});

it('rejects tiered when current does not match any tier', function () {
    $reservation = makeReservationOneRoom(qty: 1);

    $rule = PromotionRule::factory()->tieredPercentage([
        ['min' => 5, 'value' => 10],
        ['min' => 10, 'value' => 20],
    ])->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'TIER-NQ']);

    $result = app(PromoCodeService::class)->validate('TIER-NQ', $reservation, 'test@example.com');

    expect($result->valid)->toBeFalse()
        ->and($result->errorCode)->toBe(PromoCodeValidation::ERROR_DOES_NOT_APPLY);
});

it('rejects bundle price when cart qty is below bundle_qty', function () {
    $reservation = makeReservationOneRoom(qty: 1);

    $rule = PromotionRule::factory()->bundlePrice(5, 1_000_000)->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'BUNDLE-NQ']);

    $result = app(PromoCodeService::class)->validate('BUNDLE-NQ', $reservation, 'test@example.com');

    expect($result->valid)->toBeFalse()
        ->and($result->errorCode)->toBe(PromoCodeValidation::ERROR_DOES_NOT_APPLY);
});

it('allows buy x get y when qty meets buy_qty (bonus exists in preview)', function () {
    $reservation = makeReservationOneRoom(qty: 1);

    $rule = PromotionRule::factory()->buyXGetY(1, 1)->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'BOGO-OK']);

    $result = app(PromoCodeService::class)->validate('BOGO-OK', $reservation, 'test@example.com');

    expect($result->valid)->toBeTrue()
        ->and($result->bonusItems)->toBeArray()
        ->and(count($result->bonusItems))->toBe(1);
});
