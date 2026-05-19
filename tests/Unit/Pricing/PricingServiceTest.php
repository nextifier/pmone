<?php

use App\Enums\AdjustmentKind;
use App\Enums\AdjustmentValueType;
use App\Models\AppliedAdjustment;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\HotelEventAllotment;
use App\Models\PromotionRule;
use App\Models\Reservation;
use App\Models\RoomType;
use App\Services\Pricing\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeBookableReservation(array $overrides = []): Reservation
{
    $event = Event::factory()->create();
    $hotel = Hotel::factory()->withEvent($event)->create([
        'tax_percentage' => 11.00,
        'service_charge_percentage' => 0,
    ]);
    $room = RoomType::factory()->create(['hotel_id' => $hotel->id, 'base_rate' => 1000000]);
    HotelEventAllotment::factory()->create([
        'hotel_id' => $hotel->id,
        'room_type_id' => $room->id,
        'quantity' => 5,
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-31',
        'is_active' => true,
    ]);

    $reservation = Reservation::create(array_merge([
        'reservation_number' => 'HTL-TEST-'.rand(1000, 9999),
        'event_id' => $event->id,
        'hotel_id' => $hotel->id,
        'guest_name' => 'Test Guest',
        'guest_email' => 'test@example.com',
        'guest_phone' => '0812',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '1234',
        'subtotal_rooms' => 1000000,
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

    return $reservation->fresh(['hotel', 'items', 'transfers', 'adjustments']);
}

it('calculates total with subtotal + tax when no adjustments', function () {
    $reservation = makeBookableReservation();
    $service = app(PricingService::class);

    $result = $service->recalculate($reservation);

    expect($result->subtotal)->toBe(1000000.0)
        ->and($result->taxableBase)->toBe(1000000.0)
        ->and($result->penaltyAmount)->toBe(0.0)
        ->and($result->discountAmount)->toBe(0.0)
        ->and($result->taxAmount)->toBe(110000.0)
        ->and($result->totalAmount)->toBe(1110000.0);
});

it('applies percentage discount before tax', function () {
    $reservation = makeBookableReservation();
    $rule = PromotionRule::factory()->percentage(10)->create();

    AppliedAdjustment::create([
        'adjustable_type' => $reservation->getMorphClass(),
        'adjustable_id' => $reservation->id,
        'promotion_rule_id' => $rule->id,
        'kind' => AdjustmentKind::Discount->value,
        'label' => '10% off',
        'value_type' => AdjustmentValueType::Percentage->value,
        'value' => 10,
        'base_amount' => 0,
        'amount' => 0,
        'applied_by' => 'system',
    ]);

    $service = app(PricingService::class);
    $result = $service->recalculate($reservation->fresh('adjustments'));

    expect($result->discountAmount)->toBe(100000.0)
        ->and($result->taxAmount)->toBe(99000.0)
        ->and($result->totalAmount)->toBe(999000.0);
});

it('applies fixed-amount discount before tax', function () {
    $reservation = makeBookableReservation();
    $rule = PromotionRule::factory()->fixedAmount(250000)->create();

    AppliedAdjustment::create([
        'adjustable_type' => $reservation->getMorphClass(),
        'adjustable_id' => $reservation->id,
        'promotion_rule_id' => $rule->id,
        'kind' => AdjustmentKind::Discount->value,
        'label' => 'Rp 250k off',
        'value_type' => AdjustmentValueType::FixedAmount->value,
        'value' => 250000,
        'base_amount' => 0,
        'amount' => 0,
        'applied_by' => 'system',
    ]);

    $service = app(PricingService::class);
    $result = $service->recalculate($reservation->fresh('adjustments'));

    expect($result->discountAmount)->toBe(250000.0)
        ->and($result->taxAmount)->toBe(82500.0)
        ->and($result->totalAmount)->toBe(832500.0);
});

it('clamps discount to taxable base when discount exceeds subtotal', function () {
    $reservation = makeBookableReservation();
    $rule = PromotionRule::factory()->fixedAmount(5000000)->create();

    AppliedAdjustment::create([
        'adjustable_type' => $reservation->getMorphClass(),
        'adjustable_id' => $reservation->id,
        'promotion_rule_id' => $rule->id,
        'kind' => AdjustmentKind::Discount->value,
        'label' => 'Huge discount',
        'value_type' => AdjustmentValueType::FixedAmount->value,
        'value' => 5000000,
        'base_amount' => 0,
        'amount' => 0,
        'applied_by' => 'system',
    ]);

    $service = app(PricingService::class);
    $result = $service->recalculate($reservation->fresh('adjustments'));

    expect($result->discountAmount)->toBe(1000000.0)
        ->and($result->totalAmount)->toBe(0.0);
});

it('respects max_discount_amount cap on percentage rule', function () {
    $reservation = makeBookableReservation();
    $rule = PromotionRule::factory()->percentage(50)->create(['max_discount_amount' => 200000]);

    AppliedAdjustment::create([
        'adjustable_type' => $reservation->getMorphClass(),
        'adjustable_id' => $reservation->id,
        'promotion_rule_id' => $rule->id,
        'kind' => AdjustmentKind::Discount->value,
        'label' => '50% off, max 200k',
        'value_type' => AdjustmentValueType::Percentage->value,
        'value' => 50,
        'base_amount' => 0,
        'amount' => 0,
        'applied_by' => 'system',
    ]);

    $service = app(PricingService::class);
    $result = $service->recalculate($reservation->fresh('adjustments'));

    expect($result->discountAmount)->toBe(200000.0);
});

it('adds penalty before tax', function () {
    $reservation = makeBookableReservation();
    $rule = PromotionRule::factory()->penalty()->percentage(20)->create();

    AppliedAdjustment::create([
        'adjustable_type' => $reservation->getMorphClass(),
        'adjustable_id' => $reservation->id,
        'promotion_rule_id' => $rule->id,
        'kind' => AdjustmentKind::Penalty->value,
        'label' => 'Late booking +20%',
        'value_type' => AdjustmentValueType::Percentage->value,
        'value' => 20,
        'base_amount' => 0,
        'amount' => 0,
        'applied_by' => 'system',
    ]);

    $service = app(PricingService::class);
    $result = $service->recalculate($reservation->fresh('adjustments'));

    expect($result->penaltyAmount)->toBe(200000.0)
        ->and($result->taxAmount)->toBe(132000.0)
        ->and($result->totalAmount)->toBe(1332000.0);
});

it('ignores voided adjustments', function () {
    $reservation = makeBookableReservation();
    $rule = PromotionRule::factory()->percentage(50)->create();

    AppliedAdjustment::create([
        'adjustable_type' => $reservation->getMorphClass(),
        'adjustable_id' => $reservation->id,
        'promotion_rule_id' => $rule->id,
        'kind' => AdjustmentKind::Discount->value,
        'label' => 'Voided 50%',
        'value_type' => AdjustmentValueType::Percentage->value,
        'value' => 50,
        'base_amount' => 0,
        'amount' => 0,
        'applied_by' => 'system',
        'voided_at' => now(),
        'void_reason' => 'test',
    ]);

    $service = app(PricingService::class);
    $result = $service->recalculate($reservation->fresh('adjustments'));

    expect($result->discountAmount)->toBe(0.0)
        ->and($result->totalAmount)->toBe(1110000.0);
});

it('persists totals to entity', function () {
    $reservation = makeBookableReservation();
    $service = app(PricingService::class);

    $service->recalculateAndPersist($reservation);

    $reservation->refresh();
    expect((float) $reservation->tax_amount)->toBe(110000.0)
        ->and((float) $reservation->total_amount)->toBe(1110000.0);
});
