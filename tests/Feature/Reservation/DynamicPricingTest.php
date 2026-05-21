<?php

use App\Enums\PricingType;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\HotelEventAllotment;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\RoomType;
use App\Models\RoomTypePricingPeriod;
use App\Services\Reservation\ReservationService;
use App\Services\Xendit\XenditService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

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
});

test('flat pricing produces correct subtotal (regression baseline)', function () {
    $roomType = RoomType::factory()->create([
        'hotel_id' => $this->hotel->id,
        'base_rate' => 1500000,
        'pricing_type' => PricingType::Flat,
    ]);

    HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $roomType->id,
        'quantity' => 10,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-30',
        'surcharge_type' => null,
        'surcharge_amount' => null,
        'is_active' => true,
    ]);

    $service = app(ReservationService::class);

    $reservation = $service->createReservation([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'guest_name' => 'Flat',
        'guest_email' => 'flat@test.com',
        'guest_phone' => '0',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '1',
        'items' => [[
            'room_type_id' => $roomType->id,
            'check_in_date' => '2026-06-02',
            'check_out_date' => '2026-06-04',
            'qty' => 2,
        ]],
    ]);

    expect((float) $reservation->subtotal_rooms)->toBe(6000000.0);
    expect((float) $reservation->items->first()->rate_per_night)->toBe(1500000.0);
    expect((float) $reservation->items->first()->subtotal)->toBe(6000000.0);
});

test('dynamic pricing sums per-night rates across multiple periods', function () {
    $roomType = RoomType::factory()->create([
        'hotel_id' => $this->hotel->id,
        'base_rate' => 1500000,
        'pricing_type' => PricingType::Dynamic,
    ]);

    RoomTypePricingPeriod::factory()->for($roomType)->create([
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-03',
        'rate' => 1500000,
        'is_active' => true,
    ]);
    RoomTypePricingPeriod::factory()->for($roomType)->create([
        'start_date' => '2026-05-04',
        'end_date' => '2026-05-05',
        'rate' => 1800000,
        'is_active' => true,
    ]);
    RoomTypePricingPeriod::factory()->for($roomType)->create([
        'start_date' => '2026-05-06',
        'end_date' => '2026-05-10',
        'rate' => 1500000,
        'is_active' => true,
    ]);

    HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $roomType->id,
        'quantity' => 10,
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-31',
        'is_active' => true,
    ]);

    $service = app(ReservationService::class);

    // 4 nights: May 2 (1.5M) + May 3 (1.5M) + May 4 (1.8M) + May 5 (1.8M) = 6.6M
    $reservation = $service->createReservation([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'guest_name' => 'Dyn',
        'guest_email' => 'dyn@test.com',
        'guest_phone' => '0',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '1',
        'items' => [[
            'room_type_id' => $roomType->id,
            'check_in_date' => '2026-05-02',
            'check_out_date' => '2026-05-06',
            'qty' => 1,
        ]],
    ]);

    $item = $reservation->items->first();
    expect((float) $item->subtotal)->toBe(6600000.0);
    expect((float) $item->rate_per_night)->toBe(1650000.0);
    expect($item->daily_breakdown)->toHaveCount(4);
    expect($item->daily_breakdown[0])->toMatchArray(['date' => '2026-05-02', 'rate' => 1500000.0]);
    expect($item->daily_breakdown[2])->toMatchArray(['date' => '2026-05-04', 'rate' => 1800000.0]);
});

test('dynamic pricing rejects booking when a night has no covering period', function () {
    $roomType = RoomType::factory()->create([
        'hotel_id' => $this->hotel->id,
        'base_rate' => 1500000,
        'pricing_type' => PricingType::Dynamic,
    ]);

    RoomTypePricingPeriod::factory()->for($roomType)->create([
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-05',
        'rate' => 1500000,
        'is_active' => true,
    ]);

    HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $roomType->id,
        'quantity' => 10,
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-31',
        'is_active' => true,
    ]);

    $service = app(ReservationService::class);

    expect(fn () => $service->createReservation([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'guest_name' => 'Gap',
        'guest_email' => 'gap@test.com',
        'guest_phone' => '0',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '1',
        'items' => [[
            'room_type_id' => $roomType->id,
            'check_in_date' => '2026-05-04',
            'check_out_date' => '2026-05-08',
            'qty' => 1,
        ]],
    ]))->toThrow(ValidationException::class);
});

test('dynamic pricing stacks fixed allotment surcharge per night', function () {
    $roomType = RoomType::factory()->create([
        'hotel_id' => $this->hotel->id,
        'base_rate' => 1500000,
        'pricing_type' => PricingType::Dynamic,
    ]);

    RoomTypePricingPeriod::factory()->for($roomType)->create([
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-10',
        'rate' => 1500000,
        'is_active' => true,
    ]);

    HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $roomType->id,
        'quantity' => 10,
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-31',
        'surcharge_type' => 'fixed',
        'surcharge_amount' => 100000,
        'is_active' => true,
    ]);

    $service = app(ReservationService::class);

    // 2 nights × (1500000 + 100000) = 3.2M
    $reservation = $service->createReservation([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'guest_name' => 'Fix',
        'guest_email' => 'fix@test.com',
        'guest_phone' => '0',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '1',
        'items' => [[
            'room_type_id' => $roomType->id,
            'check_in_date' => '2026-05-02',
            'check_out_date' => '2026-05-04',
            'qty' => 1,
        ]],
    ]);

    expect((float) $reservation->subtotal_rooms)->toBe(3200000.0);
    expect((float) $reservation->surcharge_amount)->toBe(200000.0);
    expect((float) $reservation->items->first()->rate_per_night)->toBe(1600000.0);
});

test('dynamic pricing stacks percentage allotment surcharge per night', function () {
    $roomType = RoomType::factory()->create([
        'hotel_id' => $this->hotel->id,
        'base_rate' => 1500000,
        'pricing_type' => PricingType::Dynamic,
    ]);

    RoomTypePricingPeriod::factory()->for($roomType)->create([
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-03',
        'rate' => 1000000,
        'is_active' => true,
    ]);
    RoomTypePricingPeriod::factory()->for($roomType)->create([
        'start_date' => '2026-05-04',
        'end_date' => '2026-05-10',
        'rate' => 2000000,
        'is_active' => true,
    ]);

    HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $roomType->id,
        'quantity' => 10,
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-31',
        'surcharge_type' => 'percentage',
        'surcharge_amount' => 10,
        'is_active' => true,
    ]);

    $service = app(ReservationService::class);

    // May 2 (1M+10%=1.1M) + May 3 (1M+10%=1.1M) + May 4 (2M+10%=2.2M) = 4.4M
    $reservation = $service->createReservation([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'guest_name' => 'Pct',
        'guest_email' => 'pct@test.com',
        'guest_phone' => '0',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '1',
        'items' => [[
            'room_type_id' => $roomType->id,
            'check_in_date' => '2026-05-02',
            'check_out_date' => '2026-05-05',
            'qty' => 1,
        ]],
    ]);

    expect((float) $reservation->subtotal_rooms)->toBe(4400000.0);
    expect((float) $reservation->surcharge_amount)->toBe(400000.0);
});

test('reservation creation does not log a misleading total amount update', function () {
    $roomType = RoomType::factory()->create([
        'hotel_id' => $this->hotel->id,
        'base_rate' => 1500000,
        'pricing_type' => PricingType::Flat,
    ]);

    HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $roomType->id,
        'quantity' => 10,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-30',
        'is_active' => true,
    ]);

    $reservation = app(ReservationService::class)->createReservation([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'guest_name' => 'Log',
        'guest_email' => 'log@test.com',
        'guest_phone' => '0',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '1',
        'items' => [[
            'room_type_id' => $roomType->id,
            'check_in_date' => '2026-06-02',
            'check_out_date' => '2026-06-04',
            'qty' => 1,
        ]],
    ]);

    // The pricing recalculation during creation must not surface as an
    // "updated total amount: Rp0 -> ..." activity log entry.
    $updateLogs = Activity::query()
        ->where('subject_type', $reservation->getMorphClass())
        ->where('subject_id', $reservation->id)
        ->where('event', 'updated')
        ->get();

    foreach ($updateLogs as $log) {
        expect(array_keys($log->properties['attributes'] ?? []))
            ->not->toContain('total_amount');
    }

    expect((float) $reservation->total_amount)->toBeGreaterThan(0);
});

test('previewSubtotal returns same numbers as createReservation', function () {
    $roomType = RoomType::factory()->create([
        'hotel_id' => $this->hotel->id,
        'base_rate' => 1500000,
        'pricing_type' => PricingType::Dynamic,
    ]);

    RoomTypePricingPeriod::factory()->for($roomType)->create([
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-10',
        'rate' => 1500000,
        'is_active' => true,
    ]);

    $allotment = HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $roomType->id,
        'quantity' => 10,
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-31',
        'surcharge_type' => 'fixed',
        'surcharge_amount' => 100000,
        'is_active' => true,
    ]);

    $service = app(ReservationService::class);

    $preview = $service->previewSubtotal(
        $roomType->fresh('pricingPeriods'),
        Carbon::parse('2026-05-02'),
        Carbon::parse('2026-05-05'),
        2,
        $allotment,
    );

    // 3 nights × (1.5M + 100K) × 2 = 9.6M
    expect($preview['subtotal'])->toBe(9600000.0);
    expect($preview['surcharge'])->toBe(600000.0);
    expect($preview['rate_per_night_avg'])->toBe(1600000.0);
    expect($preview['daily_breakdown'])->toHaveCount(3);
});
