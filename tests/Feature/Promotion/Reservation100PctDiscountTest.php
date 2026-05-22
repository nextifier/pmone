<?php

use App\Enums\PaymentMethod;
use App\Enums\ReservationStatus;
use App\Jobs\Reservation\SendBookingReceivedJob;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\HotelEventAllotment;
use App\Models\PromoCode;
use App\Models\PromotionRule;
use App\Models\RoomType;
use App\Services\Reservation\ReservationService;
use App\Services\Xendit\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->event = Event::factory()->create();
    $this->hotel = Hotel::factory()->withEvent($this->event)->create([
        'tax_percentage' => 11.00,
        'service_charge_percentage' => 0,
    ]);
    $this->room = RoomType::factory()->create([
        'hotel_id' => $this->hotel->id,
        'base_rate' => 1000000,
    ]);
    HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->room->id,
        'quantity' => 5,
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-31',
        'is_active' => true,
    ]);
});

it('skips Xendit and sets status to Paid with Complimentary when 100% fixed_amount discount makes total 0', function () {
    Queue::fake();

    $rule = PromotionRule::factory()->fixedAmount(2_000_000)->create([
        'target_types' => ['Reservation'],
        'stacking_mode' => 'exclusive',
    ]);
    PromoCode::factory()->for($rule, 'promotionRule')->create([
        'code' => 'FREE100',
        'usage_limit' => null,
    ]);

    // Mock Xendit so test fails loud if it gets called
    $xenditMock = Mockery::mock(XenditService::class);
    $xenditMock->shouldNotReceive('createCheckout');
    $this->app->instance(XenditService::class, $xenditMock);

    $reservation = app(ReservationService::class)->createReservation([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'guest_name' => 'Free Guest',
        'guest_email' => 'free@example.com',
        'guest_phone' => '0812',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '1234',
        'items' => [[
            'room_type_id' => $this->room->id,
            'check_in_date' => '2026-07-01',
            'check_out_date' => '2026-07-03',
            'qty' => 1,
        ]],
        'promo_code' => 'FREE100',
    ]);

    expect($reservation->status)->toBe(ReservationStatus::Paid)
        ->and($reservation->payment_method)->toBe(PaymentMethod::Complimentary)
        ->and((float) $reservation->total_amount)->toBe(0.0)
        ->and($reservation->paid_at)->not->toBeNull()
        ->and($reservation->payment_url)->toBeNull()
        ->and($reservation->xendit_invoice_id)->toBeNull();

    Queue::assertPushed(SendBookingReceivedJob::class);
});

it('sets total 0 and skips Xendit when percentage discount makes total exactly zero', function () {
    Queue::fake();

    $rule = PromotionRule::factory()->percentage(100)->create([
        'target_types' => ['Reservation'],
        'stacking_mode' => 'exclusive',
    ]);
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'FULLOFF']);

    $xenditMock = Mockery::mock(XenditService::class);
    $xenditMock->shouldNotReceive('createCheckout');
    $this->app->instance(XenditService::class, $xenditMock);

    $reservation = app(ReservationService::class)->createReservation([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'guest_name' => 'Free Guest',
        'guest_email' => 'free@example.com',
        'guest_phone' => '0812',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '1234',
        'items' => [[
            'room_type_id' => $this->room->id,
            'check_in_date' => '2026-07-01',
            'check_out_date' => '2026-07-03',
            'qty' => 1,
        ]],
        'promo_code' => 'FULLOFF',
    ]);

    expect((float) $reservation->total_amount)->toBe(0.0)
        ->and($reservation->status)->toBe(ReservationStatus::Paid)
        ->and($reservation->payment_method)->toBe(PaymentMethod::Complimentary);
});

it('still generates Xendit invoice when partial discount leaves nonzero total', function () {
    Queue::fake();

    $rule = PromotionRule::factory()->percentage(50)->create([
        'target_types' => ['Reservation'],
        'stacking_mode' => 'exclusive',
    ]);
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'HALFOFF']);

    $xenditMock = Mockery::mock(XenditService::class);
    $xenditMock->shouldReceive('createCheckout')
        ->once()
        ->andReturn(['reference' => 'inv-123', 'payment_url' => 'https://xendit/inv-123']);
    $xenditMock->shouldReceive('gateway')->andReturn(null);
    $this->app->instance(XenditService::class, $xenditMock);

    $reservation = app(ReservationService::class)->createReservation([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'guest_name' => 'Half Guest',
        'guest_email' => 'half@example.com',
        'guest_phone' => '0812',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '1234',
        'items' => [[
            'room_type_id' => $this->room->id,
            'check_in_date' => '2026-07-01',
            'check_out_date' => '2026-07-03',
            'qty' => 1,
        ]],
        'promo_code' => 'HALFOFF',
    ], xendit: $xenditMock);

    expect((float) $reservation->total_amount)->toBeGreaterThan(0)
        ->and($reservation->status)->toBe(ReservationStatus::PendingPayment)
        ->and($reservation->payment_method)->toBe(PaymentMethod::Xendit)
        ->and($reservation->xendit_invoice_id)->toBe('inv-123');
});
