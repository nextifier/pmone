<?php

use App\Models\Event;
use App\Models\Hotel;
use App\Models\HotelEventAllotment;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\RoomType;
use App\Services\Reservation\ReservationService;
use App\Services\Xendit\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();

    $xendit = mock(XenditService::class);
    $xendit->shouldReceive('createInvoice')->andReturn([
        'invoice_id' => 'inv_x',
        'invoice_url' => 'https://x',
    ]);
    $this->app->instance(XenditService::class, $xendit);

    $this->project = Project::factory()->create(['status' => 'active']);
    ProjectPaymentGateway::factory()->for($this->project)->default()->create(['mode' => 'test']);
    $this->event = Event::factory()->create(['project_id' => $this->project->id, 'is_active' => true]);
    $this->hotel = Hotel::factory()->withEvent($this->event)->create([
        'tax_percentage' => 0,
        'service_charge_percentage' => 0,
    ]);
    $this->roomType = RoomType::factory()->create([
        'hotel_id' => $this->hotel->id,
        'base_rate' => 1000000,
    ]);
});

test('overlapping allotments resolve in deterministic order by id', function () {
    $first = HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->roomType->id,
        'quantity' => 5,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-30',
        'surcharge_type' => 'fixed',
        'surcharge_amount' => 100000,
        'is_active' => true,
    ]);

    $second = HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->roomType->id,
        'quantity' => 5,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-29',
        'surcharge_type' => 'fixed',
        'surcharge_amount' => 500000,
        'is_active' => true,
    ]);

    $service = app(ReservationService::class);

    $reservation = $service->createReservation([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'guest_name' => 'Det',
        'guest_email' => 'det@test.com',
        'guest_phone' => '0',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '1',
        'items' => [[
            'room_type_id' => $this->roomType->id,
            'check_in_date' => '2026-06-02',
            'check_out_date' => '2026-06-04',
            'qty' => 1,
        ]],
    ]);

    expect($reservation->items->first()->allotment_id)->toBe($first->id);
    expect((float) $reservation->items->first()->rate_per_night)->toBe(1100000.0);
});

test('surcharge is folded into the room price and counted once in the total', function () {
    HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->roomType->id,
        'quantity' => 5,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-30',
        'surcharge_type' => 'fixed',
        'surcharge_amount' => 100000,
        'is_active' => true,
    ]);

    $reservation = app(ReservationService::class)->createReservation([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'guest_name' => 'Det',
        'guest_email' => 'det@test.com',
        'guest_phone' => '0',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '1',
        'items' => [[
            'room_type_id' => $this->roomType->id,
            'check_in_date' => '2026-06-02',
            'check_out_date' => '2026-06-04',
            'qty' => 1,
        ]],
    ]);

    // 2 nights x (1,000,000 base + 100,000 surcharge) = 2,200,000. Tax/service
    // are 0 for this hotel, so the total must equal the room subtotal exactly -
    // the surcharge is folded in, never added a second time.
    expect((float) $reservation->subtotal_rooms)->toBe(2200000.0)
        ->and((float) $reservation->surcharge_amount)->toBe(200000.0)
        ->and((float) $reservation->total_amount)->toBe(2200000.0);
});
