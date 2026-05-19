<?php

use App\Models\AppliedAdjustment;
use App\Models\PromoCode;
use App\Models\PromotionRule;
use App\Models\User;
use App\Services\Promotion\PromoCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->scenario = qaScenario();
    $this->scenario['hotel']->update(['tax_percentage' => 0]);
});

// =====================================================
// A. Value Kind
// =====================================================
it('QA-A: discount kind reduces total via PromoCode flow', function () {
    $rule = PromotionRule::factory()->percentage(20)->discount()->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'DISC20']);

    $reservation = qaReservation($this->scenario, [
        ['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000],
    ]);
    app(PromoCodeService::class)->applyByCode('DISC20', $reservation, 'qa@example.com');

    $r = $reservation->fresh();
    expect((float) $r->discount_amount)->toBe(200_000.0)
        ->and((float) $r->total_amount)->toBe(800_000.0);
});

it('QA-A: penalty kind validates rule + can be applied via admin manual mode (not promo code customer flow)', function () {
    // Penalty rule must auto-evaluate via trigger; promo-code-driven penalties
    // aren't a typical customer flow. Confirm validate path still treats it correctly.
    $rule = PromotionRule::factory()->percentage(10)->penalty()->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'PENCODE']);

    $reservation = qaReservation($this->scenario, [
        ['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000],
    ]);
    $result = app(PromoCodeService::class)->validate('PENCODE', $reservation, 'qa@example.com');

    // System allows the code (no business gate blocking penalty kind on promo flow).
    // The validation returns valid=true and the apply would increase total.
    expect($result->valid)->toBeTrue();

    // Apply -> increases total by 10% (penalty path in PricingService)
    app(PromoCodeService::class)->applyByCode('PENCODE', $reservation, 'qa@example.com');
    $r = $reservation->fresh();
    expect((float) $r->penalty_amount)->toBe(100_000.0)
        ->and((float) $r->total_amount)->toBe(1_100_000.0);
});

it('QA-A: admin manual discount via ReservationAdjustmentController applies + can be voided', function () {
    foreach (['promotions.apply_manual', 'promotions.void_adjustment'] as $p) {
        Permission::firstOrCreate(['name' => $p]);
    }
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->givePermissionTo(['promotions.apply_manual', 'promotions.void_adjustment']);

    $reservation = qaReservation($this->scenario, [
        ['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000],
    ]);

    $response = $this->actingAs($admin)->postJson(
        "/api/events/{$this->scenario['event']->id}/reservations/{$reservation->ulid}/adjustments",
        [
            'mode' => 'manual',
            'kind' => 'discount',
            'value_type' => 'fixed_amount',
            'value' => 200_000,
            'reason' => 'Goodwill',
        ]
    );

    $response->assertCreated();
    $r = $reservation->fresh();
    expect((float) $r->discount_amount)->toBe(200_000.0)
        ->and((float) $r->total_amount)->toBe(800_000.0);

    // Void it
    $adj = AppliedAdjustment::query()->where('adjustable_id', $reservation->id)->first();
    $this->actingAs($admin)->deleteJson(
        "/api/events/{$this->scenario['event']->id}/reservations/{$reservation->ulid}/adjustments/{$adj->ulid}"
    )->assertOk();

    expect((float) $reservation->fresh()->total_amount)->toBe(1_000_000.0);
});

it('QA-A: admin manual rejects buy_x_get_y value_type (B8 guard)', function () {
    foreach (['promotions.apply_manual'] as $p) {
        Permission::firstOrCreate(['name' => $p]);
    }
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->givePermissionTo(['promotions.apply_manual']);

    $reservation = qaReservation($this->scenario, [
        ['room_type_id' => $this->scenario['room']->id, 'rate' => 1_000_000],
    ]);

    $response = $this->actingAs($admin)->postJson(
        "/api/events/{$this->scenario['event']->id}/reservations/{$reservation->ulid}/adjustments",
        [
            'mode' => 'manual',
            'kind' => 'discount',
            'value_type' => 'buy_x_get_y',
            'value' => 0,
        ]
    );

    $response->assertStatus(422)->assertJsonValidationErrors('value_type');
});
