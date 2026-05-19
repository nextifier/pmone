<?php

use App\DTOs\Promotion\PromoCodeValidation;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\HotelEventAllotment;
use App\Models\PromoCode;
use App\Models\PromoCodeUsage;
use App\Models\PromotionRule;
use App\Models\Reservation;
use App\Models\RoomType;
use App\Services\Promotion\PromoCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function makeReservationForPromo(): Reservation
{
    $event = Event::factory()->create();
    $hotel = Hotel::factory()->withEvent($event)->create(['tax_percentage' => 11.00, 'service_charge_percentage' => 0]);
    $room = RoomType::factory()->create(['hotel_id' => $hotel->id, 'base_rate' => 1000000]);
    HotelEventAllotment::factory()->create([
        'hotel_id' => $hotel->id,
        'room_type_id' => $room->id,
        'quantity' => 5,
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-31',
        'is_active' => true,
    ]);

    return Reservation::create([
        'reservation_number' => 'HTL-TEST-'.rand(1000, 9999),
        'event_id' => $event->id,
        'hotel_id' => $hotel->id,
        'guest_name' => 'Test',
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
    ]);
}

it('returns INVALID_CODE for non-existent code', function () {
    $reservation = makeReservationForPromo();
    $service = app(PromoCodeService::class);

    $result = $service->validate('NONEXISTENT', $reservation, 'test@example.com');

    expect($result->valid)->toBeFalse()
        ->and($result->errorCode)->toBe(PromoCodeValidation::ERROR_INVALID_CODE);
});

it('returns INACTIVE for disabled code', function () {
    $reservation = makeReservationForPromo();
    $rule = PromotionRule::factory()->percentage(10)->create();
    $code = PromoCode::factory()->for($rule, 'promotionRule')->create([
        'code' => 'DISABLED',
        'is_active' => false,
    ]);

    $service = app(PromoCodeService::class);
    $result = $service->validate('DISABLED', $reservation, 'test@example.com');

    expect($result->valid)->toBeFalse()
        ->and($result->errorCode)->toBe(PromoCodeValidation::ERROR_INACTIVE);
});

it('returns EXPIRED when valid_until is past', function () {
    $reservation = makeReservationForPromo();
    $rule = PromotionRule::factory()->percentage(10)->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create([
        'code' => 'EXPIRED1',
        'valid_until' => now()->subDay(),
    ]);

    $service = app(PromoCodeService::class);
    $result = $service->validate('EXPIRED1', $reservation, 'test@example.com');

    expect($result->errorCode)->toBe(PromoCodeValidation::ERROR_EXPIRED);
});

it('returns USAGE_LIMIT_REACHED when usage_count equals limit', function () {
    $reservation = makeReservationForPromo();
    $rule = PromotionRule::factory()->percentage(10)->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create([
        'code' => 'EXHAUSTED',
        'usage_limit' => 1,
        'usage_count' => 1,
    ]);

    $service = app(PromoCodeService::class);
    $result = $service->validate('EXHAUSTED', $reservation, 'test@example.com');

    expect($result->errorCode)->toBe(PromoCodeValidation::ERROR_USAGE_LIMIT_REACHED);
});

it('returns NOT_ELIGIBLE when issued_to_email does not match', function () {
    $reservation = makeReservationForPromo();
    $rule = PromotionRule::factory()->percentage(10)->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create([
        'code' => 'PRIVATE1',
        'issued_to_email' => 'vip@example.com',
    ]);

    $service = app(PromoCodeService::class);
    $result = $service->validate('PRIVATE1', $reservation, 'other@example.com');

    expect($result->errorCode)->toBe(PromoCodeValidation::ERROR_NOT_ELIGIBLE);
});

it('returns MIN_PURCHASE_NOT_MET when subtotal is below threshold', function () {
    $reservation = makeReservationForPromo();
    $rule = PromotionRule::factory()->percentage(10)->create([
        'min_purchase_amount' => 5000000,
    ]);
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'BIGORDER']);

    $service = app(PromoCodeService::class);
    $result = $service->validate('BIGORDER', $reservation, 'test@example.com');

    expect($result->errorCode)->toBe(PromoCodeValidation::ERROR_MIN_PURCHASE_NOT_MET);
});

it('returns ok for valid code', function () {
    $reservation = makeReservationForPromo();
    $rule = PromotionRule::factory()->percentage(10)->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'GOOD']);

    $service = app(PromoCodeService::class);
    $result = $service->validate('GOOD', $reservation, 'test@example.com');

    expect($result->valid)->toBeTrue()
        ->and($result->errorCode)->toBeNull()
        ->and($result->previewDiscount)->toBeGreaterThan(0);
});

it('applies code and creates adjustment + usage atomically', function () {
    $reservation = makeReservationForPromo();
    $rule = PromotionRule::factory()->percentage(10)->create();
    $code = PromoCode::factory()->for($rule, 'promotionRule')->create([
        'code' => 'APPLY1',
        'usage_limit' => 1,
        'usage_count' => 0,
    ]);

    $service = app(PromoCodeService::class);
    $adjustment = $service->applyByCode('APPLY1', $reservation, 'test@example.com');

    expect($adjustment->kind->value)->toBe('discount')
        ->and((float) $adjustment->amount)->toBe(100000.0);

    expect(PromoCode::find($code->id)->usage_count)->toBe(1);
    expect(PromoCodeUsage::where('promo_code_id', $code->id)->where('email', 'test@example.com')->count())->toBe(1);
});

it('blocks second redemption when usage_limit=1 reached', function () {
    $reservation1 = makeReservationForPromo();
    $reservation2 = makeReservationForPromo();
    $rule = PromotionRule::factory()->percentage(10)->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create([
        'code' => 'ONCE',
        'usage_limit' => 1,
    ]);

    $service = app(PromoCodeService::class);
    $service->applyByCode('ONCE', $reservation1, 'first@example.com');

    expect(fn () => $service->applyByCode('ONCE', $reservation2, 'second@example.com'))
        ->toThrow(ValidationException::class);
});

it('void reverts promo usage counter when revert_usage_on_cancel = true', function () {
    $reservation = makeReservationForPromo();
    $rule = PromotionRule::factory()->percentage(10)->create(['revert_usage_on_cancel' => true]);
    $code = PromoCode::factory()->for($rule, 'promotionRule')->create([
        'code' => 'REVERT',
        'usage_limit' => 5,
    ]);

    $service = app(PromoCodeService::class);
    $adjustment = $service->applyByCode('REVERT', $reservation, 'test@example.com');

    expect(PromoCode::find($code->id)->usage_count)->toBe(1);

    $service->void($adjustment, 'test_revert');

    expect(PromoCode::find($code->id)->usage_count)->toBe(0)
        ->and($adjustment->fresh()->voided_at)->not->toBeNull();
});

it('does not revert when revert_usage_on_cancel = false', function () {
    $reservation = makeReservationForPromo();
    $rule = PromotionRule::factory()->percentage(10)->create(['revert_usage_on_cancel' => false]);
    $code = PromoCode::factory()->for($rule, 'promotionRule')->create([
        'code' => 'BURNED',
        'usage_limit' => 5,
    ]);

    $service = app(PromoCodeService::class);
    $adjustment = $service->applyByCode('BURNED', $reservation, 'test@example.com');

    $service->void($adjustment, 'test_burn');

    expect(PromoCode::find($code->id)->usage_count)->toBe(1);
});

it('normalizes code to uppercase + trims whitespace', function () {
    $reservation = makeReservationForPromo();
    $rule = PromotionRule::factory()->percentage(10)->create();
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'WELCOME']);

    $service = app(PromoCodeService::class);
    $result = $service->validate('  welcome  ', $reservation, 'test@example.com');

    expect($result->valid)->toBeTrue();
});

it('bulk generates unique codes', function () {
    $rule = PromotionRule::factory()->percentage(10)->create();
    $service = app(PromoCodeService::class);

    $codes = $service->bulkGenerate($rule, 25, ['length' => 8, 'prefix' => 'BG-']);

    expect($codes)->toHaveCount(25);
    $values = $codes->pluck('code')->all();
    expect(array_unique($values))->toHaveCount(25);
    foreach ($values as $code) {
        expect($code)->toStartWith('BG-');
    }
});
