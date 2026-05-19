<?php

use App\Enums\AdjustmentValueType;
use App\Models\HotelEventAllotment;
use App\Models\PromoCode;
use App\Models\PromotionRule;
use App\Models\RoomType;
use App\Services\Promotion\PromoCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->scenario = qaScenario();
});

// =====================================================
// B1. PERCENTAGE
// =====================================================
it('B1: percentage 10% applies to 1M subtotal -> 100k discount', function () {
    $rule = PromotionRule::factory()->percentage(10)->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'PCT10']);

    $reservation = qaReservation($this->scenario, [
        ['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000],
    ]);

    $adj = app(PromoCodeService::class)->applyByCode('PCT10', $reservation, 'qa@example.com');

    expect((float) $adj->amount)->toBe(100_000.0);
});

it('B1: 100% percentage produces total_amount=0', function () {
    $rule = PromotionRule::factory()->percentage(100)->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'PCT100']);

    $reservation = qaReservation($this->scenario, [
        ['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000],
    ]);

    app(PromoCodeService::class)->applyByCode('PCT100', $reservation, 'qa@example.com');

    expect((float) $reservation->fresh()->total_amount)->toBe(0.0);
});

it('B1: 0% percentage applies but discount=0', function () {
    $rule = PromotionRule::factory()->percentage(0)->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'PCT0']);

    $reservation = qaReservation($this->scenario, [
        ['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000],
    ]);

    $adj = app(PromoCodeService::class)->applyByCode('PCT0', $reservation, 'qa@example.com');

    expect((float) $adj->amount)->toBe(0.0);
});

// =====================================================
// B2. FIXED AMOUNT
// =====================================================
it('B2: fixed 200k discount applies clean to 1M subtotal -> total 1M-200k+tax', function () {
    $rule = PromotionRule::factory()->fixedAmount(200_000)->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'FIX200']);

    $reservation = qaReservation($this->scenario, [
        ['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000],
    ]);

    $adj = app(PromoCodeService::class)->applyByCode('FIX200', $reservation, 'qa@example.com');

    expect((float) $adj->amount)->toBe(200_000.0)
        ->and((float) $reservation->fresh()->discount_amount)->toBe(200_000.0);
});

it('B2: fixed amount > subtotal clamps to subtotal (total >= 0)', function () {
    $rule = PromotionRule::factory()->fixedAmount(5_000_000)->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'FIXOVER']);

    $reservation = qaReservation($this->scenario, [
        ['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000],
    ]);

    app(PromoCodeService::class)->applyByCode('FIXOVER', $reservation, 'qa@example.com');

    expect((float) $reservation->fresh()->total_amount)->toBe(0.0);
});

// =====================================================
// B3. BUY X GET Y
// =====================================================
it('B3: BOGO 1x1 with qty=1 -> bonus allocated, customer pays for 1', function () {
    $rule = PromotionRule::factory()->buyXGetY(1, 1)->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'BOGO11']);

    $reservation = qaReservation($this->scenario, [
        ['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000, 'qty' => 1],
    ], ['guest_email' => 'qa@example.com']);

    // Hotel default tax_percentage=11 — override to 0 for clean numbers
    $reservation->hotel->update(['tax_percentage' => 0]);

    app(PromoCodeService::class)->applyByCode('BOGO11', $reservation, 'qa@example.com');
    $r = $reservation->fresh()->load('items');

    expect((int) $r->items->first()->qty)->toBe(2)
        ->and((float) $r->discount_amount)->toBe(1_000_000.0)
        ->and((float) $r->total_amount)->toBe(1_000_000.0);
});

it('B3: BOGO with qty < buy_qty -> rejected (DOES_NOT_APPLY)', function () {
    $rule = PromotionRule::factory()->buyXGetY(3, 1)->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'BOGO31']);

    $reservation = qaReservation($this->scenario, [
        ['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000, 'qty' => 2],
    ]);

    $result = app(PromoCodeService::class)->validate('BOGO31', $reservation, 'qa@example.com');

    expect($result->valid)->toBeFalse()
        ->and($result->errorCode)->toBe('DOES_NOT_APPLY');
});

it('B3: BOGO with applicability.room_types only bonuses matching item', function () {
    $room2 = RoomType::factory()->create([
        'hotel_id' => $this->scenario['hotel']->id,
        'base_rate' => 500_000,
    ]);
    HotelEventAllotment::factory()->create([
        'hotel_id' => $this->scenario['hotel']->id,
        'room_type_id' => $room2->id,
        'quantity' => 10,
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-31',
        'is_active' => true,
    ]);

    $rule = PromotionRule::factory()->buyXGetY(1, 1)->create([
        'applicability' => ['room_types' => [$this->scenario['room']->id]],
    ]);
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'BOGORT']);

    $reservation = qaReservation($this->scenario, [
        ['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000, 'qty' => 1],
        ['room_type_id' => $room2->id, 'rate' => 500_000, 'qty' => 1],
    ]);
    $reservation->hotel->update(['tax_percentage' => 0]);

    app(PromoCodeService::class)->applyByCode('BOGORT', $reservation, 'qa@example.com');
    $r = $reservation->fresh()->load('items');

    $eligibleItem = $r->items->firstWhere('room_type_id', $this->scenario['room']->id);
    $ineligibleItem = $r->items->firstWhere('room_type_id', $room2->id);

    expect((int) $eligibleItem->qty)->toBe(2)
        ->and((int) $ineligibleItem->qty)->toBe(1);
});

// =====================================================
// B4. TIERED PERCENTAGE
// =====================================================
it('B4: tiered percentage matches highest qualifying tier (qty=10 -> 15%)', function () {
    $rule = PromotionRule::factory()->tieredPercentage([
        ['min' => 3, 'value' => 5],
        ['min' => 5, 'value' => 10],
        ['min' => 10, 'value' => 15],
    ])->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'TIER15']);

    $reservation = qaReservation($this->scenario, [
        ['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000, 'qty' => 10],
    ]);
    $reservation->hotel->update(['tax_percentage' => 0]);

    app(PromoCodeService::class)->applyByCode('TIER15', $reservation, 'qa@example.com');

    // 10M * 15% = 1.5M
    expect((float) $reservation->fresh()->discount_amount)->toBe(1_500_000.0);
});

it('B4: tiered with qty below lowest tier -> rejected', function () {
    $rule = PromotionRule::factory()->tieredPercentage([
        ['min' => 5, 'value' => 10],
    ])->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'TIERNO']);

    $reservation = qaReservation($this->scenario, [
        ['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000, 'qty' => 2],
    ]);

    $result = app(PromoCodeService::class)->validate('TIERNO', $reservation, 'qa@example.com');

    expect($result->valid)->toBeFalse()
        ->and($result->errorCode)->toBe('DOES_NOT_APPLY');
});

// =====================================================
// B5. TIERED FIXED AMOUNT
// =====================================================
it('B5: tiered fixed amount picks 500k tier when qty=7', function () {
    $rule = PromotionRule::factory()->create([
        'value_type' => AdjustmentValueType::TieredFixedAmount,
        'value' => 0,
        'value_config' => [
            'metric' => 'qty',
            'tiers' => [
                ['min' => 3, 'value' => 100_000],
                ['min' => 5, 'value' => 500_000],
                ['min' => 10, 'value' => 1_000_000],
            ],
        ],
    ]);
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'TIERFIX']);

    $reservation = qaReservation($this->scenario, [
        ['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000, 'qty' => 7],
    ]);
    $reservation->hotel->update(['tax_percentage' => 0]);

    app(PromoCodeService::class)->applyByCode('TIERFIX', $reservation, 'qa@example.com');

    expect((float) $reservation->fresh()->discount_amount)->toBe(500_000.0);
});

// =====================================================
// B6. BUNDLE PRICE
// =====================================================
it('B6: bundle 3-for-250k applied on qty=4 gives 1 bundle, 50k saving (3x100k -> 250k bundle)', function () {
    // Override room to 100k for clean math
    $this->scenario['room']->update(['base_rate' => 100_000]);

    $rule = PromotionRule::factory()->bundlePrice(3, 250_000)->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'BUNDLE']);

    $reservation = qaReservation($this->scenario, [
        ['room_type_id' => $this->scenario['room']->id, 'rate' => 100_000, 'qty' => 4],
    ]);
    $reservation->hotel->update(['tax_percentage' => 0]);

    app(PromoCodeService::class)->applyByCode('BUNDLE', $reservation, 'qa@example.com');

    // 4 units, 1 bundle of 3 (cheapest 3=300k) priced 250k -> 50k saved
    expect((float) $reservation->fresh()->discount_amount)->toBe(50_000.0);
});

it('B6: bundle qty < bundle_qty -> rejected', function () {
    $rule = PromotionRule::factory()->bundlePrice(5, 1_000_000)->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'BUNDLENO']);

    $reservation = qaReservation($this->scenario, [
        ['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000, 'qty' => 3],
    ]);

    $result = app(PromoCodeService::class)->validate('BUNDLENO', $reservation, 'qa@example.com');

    expect($result->valid)->toBeFalse()
        ->and($result->errorCode)->toBe('DOES_NOT_APPLY');
});

// =====================================================
// B7. FREE ADDON (transfer)
// =====================================================
it('B7: free_addon discounts the cheapest transfer line', function () {
    $transferA = qaTransferOption($this->scenario['hotel'], 300_000);
    $transferB = qaTransferOption($this->scenario['hotel'], 500_000);

    $rule = PromotionRule::factory()->freeAddon(1, ['transfer'])->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'FREEADD']);

    $reservation = qaReservation($this->scenario, [
        ['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000],
    ]);
    $reservation->hotel->update(['tax_percentage' => 0]);

    // Attach transfers
    $reservation->transfers()->create([
        'transfer_option_id' => $transferA->id,
        'direction' => 'in',
        'transfer_date' => '2026-07-10',
        'pax_count' => 1,
        'price' => 300_000,
    ]);
    $reservation->transfers()->create([
        'transfer_option_id' => $transferB->id,
        'direction' => 'out',
        'transfer_date' => '2026-07-11',
        'pax_count' => 1,
        'price' => 500_000,
    ]);
    $reservation->update(['subtotal_transfer' => 800_000]);

    app(PromoCodeService::class)->applyByCode('FREEADD', $reservation->fresh(['items', 'transfers', 'hotel']), 'qa@example.com');

    // Discounts cheapest (300k)
    expect((float) $reservation->fresh()->discount_amount)->toBe(300_000.0);
});
