<?php

use App\DTOs\Promotion\PromoCodeValidation;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\HotelEvent;
use App\Models\HotelEventAllotment;
use App\Models\PromoCode;
use App\Models\PromotionRule;
use App\Models\Reservation;
use App\Models\RoomType;
use App\Services\Promotion\PromoCodeService;
use App\Services\Reservation\TransientReservationBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->event = Event::factory()->create();
    $this->hotel = Hotel::factory()->withEvent($this->event)->create([
        'tax_percentage' => 0,
        'service_charge_percentage' => 0,
    ]);
    HotelEvent::query()->updateOrCreate(
        ['hotel_id' => $this->hotel->id, 'event_id' => $this->event->id],
        ['is_active' => true]
    );
    $this->room = RoomType::factory()->create(['hotel_id' => $this->hotel->id, 'base_rate' => 1_000_000]);
    HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->room->id,
        'quantity' => 10,
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-31',
        'is_active' => true,
    ]);
});

function buildPreviewReservation(): Reservation
{
    return app(TransientReservationBuilder::class)->build([
        'hotel_id' => test()->hotel->id,
        'event_id' => test()->event->id,
        'guest_email' => 'guest@askindo.com',
        'items' => [[
            'room_type_id' => test()->room->id,
            'check_in_date' => '2026-07-10',
            'check_out_date' => '2026-07-12',
            'qty' => 2,
        ]],
    ]);
}

it('applies promo when guest_email_domain matches', function () {
    $rule = PromotionRule::factory()->percentage(10)->create([
        'applicability' => ['guest_email_domains' => ['@askindo.com']],
    ]);
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'DOMAIN1']);

    $result = app(PromoCodeService::class)->validate('DOMAIN1', buildPreviewReservation(), 'guest@askindo.com');

    expect($result->valid)->toBeTrue();
});

it('rejects promo when guest_email_domain does not match', function () {
    $rule = PromotionRule::factory()->percentage(10)->create([
        'applicability' => ['guest_email_domains' => ['@askindo.com']],
    ]);
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'DOMAIN2']);

    $result = app(PromoCodeService::class)->validate('DOMAIN2', buildPreviewReservation(), 'someone@gmail.com');

    expect($result->valid)->toBeFalse()
        ->and($result->errorCode)->toBe(PromoCodeValidation::ERROR_DOES_NOT_APPLY);
});

it('applies promo when min_nights met', function () {
    // Reservation: 2 nights × 2 qty = 4 nights aggregate
    $rule = PromotionRule::factory()->percentage(10)->create([
        'applicability' => ['min_nights' => 2],
    ]);
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'NIGHTS']);

    $result = app(PromoCodeService::class)->validate('NIGHTS', buildPreviewReservation(), 'guest@askindo.com');

    expect($result->valid)->toBeTrue();
});

it('rejects promo when min_qty not met', function () {
    $rule = PromotionRule::factory()->percentage(10)->create([
        'applicability' => ['min_qty' => 5],
    ]);
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'BIGQTY']);

    $result = app(PromoCodeService::class)->validate('BIGQTY', buildPreviewReservation(), 'guest@askindo.com');

    expect($result->valid)->toBeFalse()
        ->and($result->errorCode)->toBe(PromoCodeValidation::ERROR_DOES_NOT_APPLY);
});

it('applies promo when weekday matches today', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-13')); // Monday (ISO 1)

    $rule = PromotionRule::factory()->percentage(10)->create([
        'applicability' => ['weekdays' => [1, 2]],
    ]);
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'WEEKDAY']);

    $result = app(PromoCodeService::class)->validate('WEEKDAY', buildPreviewReservation(), 'guest@askindo.com');

    expect($result->valid)->toBeTrue();

    Carbon::setTestNow();
});

it('rejects promo when weekday does not match today', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-15')); // Wednesday (ISO 3)

    $rule = PromotionRule::factory()->percentage(10)->create([
        'applicability' => ['weekdays' => [1, 2]],
    ]);
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'WEEKDAY2']);

    $result = app(PromoCodeService::class)->validate('WEEKDAY2', buildPreviewReservation(), 'guest@askindo.com');

    expect($result->valid)->toBeFalse();

    Carbon::setTestNow();
});
