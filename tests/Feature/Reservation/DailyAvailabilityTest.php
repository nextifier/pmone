<?php

use App\Enums\PricingType;
use App\Enums\ReservationStatus;
use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\HotelEventAllotment;
use App\Models\Project;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\RoomType;
use App\Models\RoomTypePricingPeriod;
use App\Services\Reservation\ReservationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->project = Project::factory()->create(['status' => 'active']);
    $this->event = Event::factory()->create([
        'project_id' => $this->project->id,
        'is_active' => true,
    ]);
    $this->hotel = Hotel::factory()->withEvent($this->event)->create([
        'tax_percentage' => 11,
        'service_charge_percentage' => 5,
        'is_active' => true,
    ]);
    $this->roomType = RoomType::factory()->create([
        'hotel_id' => $this->hotel->id,
        'base_rate' => 1_500_000,
        'pricing_type' => PricingType::Flat,
        'is_active' => true,
    ]);

    $this->service = app(ReservationService::class);

    $this->consumer = ApiConsumer::create([
        'name' => 'Test',
        'website_url' => 'https://test.com',
        'allowed_origins' => [],
        'rate_limit' => 1000,
        'is_active' => true,
    ]);
    $this->headers = ['X-API-Key' => $this->consumer->api_key];
});

test('flat pricing returns base_rate for each date', function () {
    HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->roomType->id,
        'quantity' => 5,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-10',
    ]);

    $rows = $this->service->dailyAvailability(
        $this->hotel,
        $this->roomType,
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-06-03'),
    );

    expect($rows)->toHaveCount(3)
        ->and($rows[0])->toMatchArray(['date' => '2026-06-01', 'rate' => 1_500_000.0, 'available' => 5])
        ->and($rows[1])->toMatchArray(['date' => '2026-06-02', 'rate' => 1_500_000.0, 'available' => 5])
        ->and($rows[2])->toMatchArray(['date' => '2026-06-03', 'rate' => 1_500_000.0, 'available' => 5]);
});

test('dynamic pricing returns rate from covering period', function () {
    $this->roomType->update(['pricing_type' => PricingType::Dynamic]);

    RoomTypePricingPeriod::factory()->create([
        'room_type_id' => $this->roomType->id,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-05',
        'rate' => 2_000_000,
        'is_active' => true,
    ]);

    HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->roomType->id,
        'quantity' => 3,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-10',
    ]);

    $rows = $this->service->dailyAvailability(
        $this->hotel,
        $this->roomType->fresh(),
        Carbon::parse('2026-06-02'),
        Carbon::parse('2026-06-04'),
    );

    expect($rows)->toHaveCount(3);
    foreach ($rows as $row) {
        expect($row['rate'])->toBe(2_000_000.0);
        expect($row['available'])->toBe(3);
    }
});

test('dynamic pricing returns null rate and zero available when period does not cover', function () {
    $this->roomType->update(['pricing_type' => PricingType::Dynamic]);

    RoomTypePricingPeriod::factory()->create([
        'room_type_id' => $this->roomType->id,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-02',
        'rate' => 1_500_000,
        'is_active' => true,
    ]);

    HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->roomType->id,
        'quantity' => 5,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-10',
    ]);

    $rows = $this->service->dailyAvailability(
        $this->hotel,
        $this->roomType->fresh(),
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-06-04'),
    );

    expect($rows[0]['rate'])->toBe(1_500_000.0)
        ->and($rows[1]['rate'])->toBe(1_500_000.0)
        ->and($rows[2]['rate'])->toBeNull()
        ->and($rows[2]['available'])->toBe(0)
        ->and($rows[3]['rate'])->toBeNull()
        ->and($rows[3]['available'])->toBe(0);
});

test('returns zero available when no allotment and unlimited_booking is false', function () {
    $rows = $this->service->dailyAvailability(
        $this->hotel,
        $this->roomType,
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-06-02'),
    );

    foreach ($rows as $row) {
        expect($row['available'])->toBe(0);
    }
});

test('returns 999 available when no allotment and unlimited_booking is true', function () {
    $this->hotel->update(['settings' => ['allow_unlimited_booking' => true]]);

    $rows = $this->service->dailyAvailability(
        $this->hotel->fresh(),
        $this->roomType,
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-06-02'),
    );

    foreach ($rows as $row) {
        expect($row['available'])->toBe(999);
    }
});

test('subtracts committed paid reservation items from allotment', function () {
    HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->roomType->id,
        'quantity' => 10,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-10',
    ]);

    $reservation = Reservation::factory()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'status' => ReservationStatus::Paid,
    ]);

    ReservationItem::factory()->create([
        'reservation_id' => $reservation->id,
        'room_type_id' => $this->roomType->id,
        'check_in_date' => '2026-06-02',
        'check_out_date' => '2026-06-05',
        'qty' => 3,
    ]);

    $rows = $this->service->dailyAvailability(
        $this->hotel,
        $this->roomType,
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-06-06'),
    );

    expect($rows[0]['available'])->toBe(10)
        ->and($rows[1]['available'])->toBe(7)
        ->and($rows[2]['available'])->toBe(7)
        ->and($rows[3]['available'])->toBe(7)
        ->and($rows[4]['available'])->toBe(10)
        ->and($rows[5]['available'])->toBe(10);
});

test('expired pending payment is not counted as committed', function () {
    HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->roomType->id,
        'quantity' => 5,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-10',
    ]);

    $expired = Reservation::factory()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'status' => ReservationStatus::PendingPayment,
        'payment_expires_at' => now()->subHour(),
    ]);

    ReservationItem::factory()->create([
        'reservation_id' => $expired->id,
        'room_type_id' => $this->roomType->id,
        'check_in_date' => '2026-06-02',
        'check_out_date' => '2026-06-04',
        'qty' => 2,
    ]);

    $rows = $this->service->dailyAvailability(
        $this->hotel,
        $this->roomType,
        Carbon::parse('2026-06-02'),
        Carbon::parse('2026-06-03'),
    );

    foreach ($rows as $row) {
        expect($row['available'])->toBe(5);
    }
});

test('fixed surcharge is added to rate', function () {
    HotelEventAllotment::factory()
        ->withSurcharge('fixed', 250_000)
        ->create([
            'hotel_id' => $this->hotel->id,
            'room_type_id' => $this->roomType->id,
            'quantity' => 5,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-10',
        ]);

    $rows = $this->service->dailyAvailability(
        $this->hotel,
        $this->roomType,
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-06-01'),
    );

    expect($rows[0]['rate'])->toBe(1_750_000.0);
});

test('percentage surcharge multiplies the rate', function () {
    HotelEventAllotment::factory()
        ->withSurcharge('percentage', 10)
        ->create([
            'hotel_id' => $this->hotel->id,
            'room_type_id' => $this->roomType->id,
            'quantity' => 5,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-10',
        ]);

    $rows = $this->service->dailyAvailability(
        $this->hotel,
        $this->roomType,
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-06-01'),
    );

    expect($rows[0]['rate'])->toBe(1_650_000.0);
});

test('endpoint validates end_date is after_or_equal start_date', function () {
    $response = $this->getJson(
        "/api/public/events/{$this->event->slug}/hotels/{$this->hotel->slug}/room-types/{$this->roomType->id}/daily-availability?start_date=2026-06-10&end_date=2026-06-01",
        $this->headers
    );

    $response->assertStatus(422);
});

test('endpoint validates max range 92 days', function () {
    $response = $this->getJson(
        "/api/public/events/{$this->event->slug}/hotels/{$this->hotel->slug}/room-types/{$this->roomType->id}/daily-availability?start_date=2026-01-01&end_date=2026-06-01",
        $this->headers
    );

    $response->assertStatus(422);
});
