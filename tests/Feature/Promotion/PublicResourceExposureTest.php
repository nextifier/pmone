<?php

use App\Http\Resources\PublicReservationResource;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\HotelEvent;
use App\Models\HotelEventAllotment;
use App\Models\PromoCode;
use App\Models\PromotionRule;
use App\Models\RoomType;
use App\Services\Reservation\ReservationService;
use App\Services\Xendit\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('PublicReservationResource exposes discount + surcharge + promo_code_applied', function () {
    Queue::fake();

    $event = Event::factory()->create(['hotel_reservation_enabled' => true]);
    $hotel = Hotel::factory()->withEvent($event)->create([
        'tax_percentage' => 11,
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
        'target_types' => ['Reservation'],
    ]);
    PromoCode::factory()->for($rule, 'promotionRule')->create(['code' => 'PUBVIEW']);

    $xendit = Mockery::mock(XenditService::class);
    $xendit->shouldReceive('createCheckout')->andReturn(['reference' => 'inv-1', 'payment_url' => 'https://x/inv-1']);
    $xendit->shouldReceive('gateway')->andReturn(null);

    $reservation = app(ReservationService::class)->createReservation([
        'hotel_id' => $hotel->id,
        'event_id' => $event->id,
        'guest_name' => 'Guest',
        'guest_email' => 'guest@example.com',
        'guest_phone' => '0812',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '1234',
        'items' => [[
            'room_type_id' => $room->id,
            'check_in_date' => '2026-07-10',
            'check_out_date' => '2026-07-12',
            'qty' => 1,
        ]],
        'promo_code' => 'PUBVIEW',
    ], xendit: $xendit);

    $resource = (new PublicReservationResource($reservation->fresh(['items', 'transfers', 'hotel'])))->resolve();

    expect($resource['amounts'])->toHaveKeys(['subtotal_rooms', 'subtotal_transfer', 'surcharge', 'penalty', 'discount', 'tax', 'service', 'total'])
        ->and((float) $resource['amounts']['discount'])->toBeGreaterThan(0)
        ->and($resource['promo_code_applied'])->toBe('PUBVIEW');
});
