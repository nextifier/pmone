<?php

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

function bootstrapReservationWith(array $itemsSpec): Reservation
{
    $event = Event::factory()->create();
    $hotel = Hotel::factory()->withEvent($event)->create([
        'tax_percentage' => 0,
        'service_charge_percentage' => 0,
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
        'subtotal_rooms' => 0,
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

    $subtotal = 0;
    foreach ($itemsSpec as $spec) {
        $room = RoomType::factory()->create(['hotel_id' => $hotel->id, 'base_rate' => $spec['rate']]);
        HotelEventAllotment::factory()->create([
            'hotel_id' => $hotel->id,
            'room_type_id' => $room->id,
            'quantity' => 10,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'is_active' => true,
        ]);

        $qty = $spec['qty'] ?? 1;
        $nights = $spec['nights'] ?? 1;
        $lineSubtotal = $spec['rate'] * $nights * $qty;
        $subtotal += $lineSubtotal;

        ReservationItem::factory()->create([
            'reservation_id' => $reservation->id,
            'room_type_id' => $room->id,
            'rate_per_night' => $spec['rate'],
            'nights' => $nights,
            'qty' => $qty,
            'subtotal' => $lineSubtotal,
            'check_in_date' => '2026-07-10',
            'check_out_date' => '2026-07-'.(10 + $nights),
        ]);
    }

    $reservation->update(['subtotal_rooms' => $subtotal]);

    return $reservation->fresh(['items', 'transfers', 'hotel']);
}

it('applies buy x get y - bonus qty auto-allocated, customer pays original amount', function () {
    // Approach A semantics: customer puts 1 room in cart, BOGO adds 1 bonus room.
    // After apply: cart shows qty=2, but customer pays for 1 (discount equals bonus value).
    $reservation = bootstrapReservationWith([
        ['rate' => 1000000, 'qty' => 1, 'nights' => 1],
    ]);

    $rule = PromotionRule::factory()->buyXGetY(1, 1)->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'BOGO']);

    app(PromoCodeService::class)->applyByCode('BOGO', $reservation, 'test@example.com');

    $reservation->refresh()->load('items');

    // qty bumped from 1 to 2 (1 paid + 1 bonus)
    expect((int) $reservation->items->first()->qty)->toBe(2);
    // subtotal_rooms reflects new qty
    expect((float) $reservation->subtotal_rooms)->toBe(2000000.0);
    // discount cancels the bonus (1× unit price)
    expect((float) $reservation->discount_amount)->toBe(1000000.0);
    // customer pays for 1 unit
    expect((float) $reservation->total_amount)->toBe(1000000.0);
});

it('reverts bonus qty when buy x get y promo is voided', function () {
    $reservation = bootstrapReservationWith([
        ['rate' => 1000000, 'qty' => 1, 'nights' => 1],
    ]);

    $rule = PromotionRule::factory()->buyXGetY(1, 1)->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'BOGO-REVERT']);

    $service = app(PromoCodeService::class);
    $adj = $service->applyByCode('BOGO-REVERT', $reservation, 'test@example.com');

    $reservation->refresh()->load('items');
    expect((int) $reservation->items->first()->qty)->toBe(2);

    $service->void($adj, 'test-revert');

    $reservation->refresh()->load('items');
    // qty restored to original
    expect((int) $reservation->items->first()->qty)->toBe(1);
    expect((float) $reservation->subtotal_rooms)->toBe(1000000.0);
});

it('applies tiered percentage - matches highest qualifying tier', function () {
    $reservation = bootstrapReservationWith([
        ['rate' => 1000000, 'qty' => 5, 'nights' => 1],
    ]);

    $rule = PromotionRule::factory()->tieredPercentage([
        ['min' => 3, 'value' => 5],
        ['min' => 5, 'value' => 10],
        ['min' => 10, 'value' => 15],
    ])->create();
    $code = PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'TIER1']);

    app(PromoCodeService::class)->applyByCode('TIER1', $reservation, 'test@example.com');

    $reservation->refresh();

    // 5 units total → matches min=5 tier (10% off 5M = 500k)
    expect((float) $reservation->discount_amount)->toBe(500000.0);
});

it('applies bundle price - 3 for fixed price', function () {
    $reservation = bootstrapReservationWith([
        ['rate' => 100000, 'qty' => 4, 'nights' => 1],
    ]);

    $rule = PromotionRule::factory()->bundlePrice(3, 250000)->create();
    $code = PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'BUNDLE1']);

    app(PromoCodeService::class)->applyByCode('BUNDLE1', $reservation, 'test@example.com');

    $reservation->refresh();

    // 4 units, 1 bundle of 3 (cheapest 3 = 300k) priced at 250k = 50k discount
    expect((float) $reservation->discount_amount)->toBe(50000.0);
    expect((float) $reservation->total_amount)->toBe(350000.0);
});

it('saves bonus_allocations on rule_snapshot after apply', function () {
    // Approach A: bonus allocation tracked in rule_snapshot so void can revert
    // the ReservationItem.qty back to user's original intent.
    $reservation = bootstrapReservationWith([
        ['rate' => 800000, 'qty' => 1],
    ]);

    $rule = PromotionRule::factory()->buyXGetY(1, 1)->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'BOGO2']);

    app(PromoCodeService::class)->applyByCode('BOGO2', $reservation, 'test@example.com');

    $adj = $reservation->fresh()->adjustments()->first();
    $allocations = $adj->rule_snapshot['bonus_allocations'] ?? null;

    expect($allocations)->toBeArray()
        ->and(count($allocations))->toBe(1)
        ->and((int) $allocations[0]['original_qty'])->toBe(1)
        ->and((int) $allocations[0]['bonus_qty'])->toBe(1)
        ->and((int) $allocations[0]['new_qty'])->toBe(2);
});
