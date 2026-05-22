<?php

use App\Jobs\Reservation\ExpireUnpaidReservationsJob;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\HotelEventAllotment;
use App\Models\PromoCode;
use App\Models\PromotionRule;
use App\Models\RoomType;
use App\Services\Promotion\PromoCodeService;
use App\Services\Reservation\ReservationService;
use App\Services\Xendit\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function setupReservationWithCode(int $usageLimit = 1, bool $revertOnCancel = true): array
{
    Queue::fake();

    $event = Event::factory()->create();
    $hotel = Hotel::factory()->withEvent($event)->create([
        'tax_percentage' => 0,
        'service_charge_percentage' => 0,
    ]);
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
        'target_types' => ['Reservation'],
        'revert_usage_on_cancel' => $revertOnCancel,
    ]);
    $code = PromoCode::factory()->for($rule, 'promotionRule')->create([
        'code' => 'EXPCODE',
        'usage_limit' => $usageLimit,
    ]);

    $xendit = Mockery::mock(XenditService::class);
    $xendit->shouldReceive('createCheckout')->andReturn(['reference' => 'inv-x', 'payment_url' => 'https://x']);
    $xendit->shouldReceive('gateway')->andReturn(null);

    $reservation = app(ReservationService::class)->createReservation([
        'hotel_id' => $hotel->id,
        'event_id' => $event->id,
        'guest_name' => 'Test',
        'guest_email' => 'test@example.com',
        'guest_phone' => '0812',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '1234',
        'items' => [[
            'room_type_id' => $room->id,
            'check_in_date' => '2026-07-10',
            'check_out_date' => '2026-07-11',
            'qty' => 1,
        ]],
        'promo_code' => 'EXPCODE',
    ], xendit: $xendit);

    return [$reservation, $code];
}

it('B15: expireReservation reverts promo usage_count when revert_usage_on_cancel = true', function () {
    [$reservation, $code] = setupReservationWithCode(usageLimit: 1, revertOnCancel: true);

    expect((int) $code->fresh()->usage_count)->toBe(1);

    app(ReservationService::class)->expireReservation($reservation);

    expect((int) $code->fresh()->usage_count)->toBe(0)
        ->and($reservation->fresh()->status->value)->toBe('expired');
});

it('B15: ExpireUnpaidReservationsJob reverts promo usage for batch-expired reservations', function () {
    [$reservation, $code] = setupReservationWithCode(usageLimit: 1, revertOnCancel: true);

    // Force payment_expires_at into the past
    $reservation->forceFill(['payment_expires_at' => now()->subMinute()])->save();

    $affected = (new ExpireUnpaidReservationsJob)->handle(app(PromoCodeService::class));

    expect($affected)->toBe(1)
        ->and((int) $code->fresh()->usage_count)->toBe(0)
        ->and($reservation->fresh()->status->value)->toBe('expired');
});

it('B15: expireReservation respects revert_usage_on_cancel = false', function () {
    [$reservation, $code] = setupReservationWithCode(usageLimit: 1, revertOnCancel: false);

    app(ReservationService::class)->expireReservation($reservation);

    // Counter preserved because rule says don't revert
    expect((int) $code->fresh()->usage_count)->toBe(1);
});
