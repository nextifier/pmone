<?php

use App\Models\Event;
use App\Models\Hotel;
use App\Models\HotelEventAllotment;
use App\Models\RoomType;
use App\Models\RoomTypePricingPeriod;
use App\Services\Reservation\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->event = Event::factory()->create();
    $this->hotel = Hotel::factory()->withEvent($this->event)->create([
        'tax_percentage' => 0,
        'service_charge_percentage' => 0,
    ]);
    $this->room = RoomType::factory()->create([
        'hotel_id' => $this->hotel->id,
        'base_rate' => 1_000_000,
    ]);
});

test('base_rate_override replaces room base rate in preview', function () {
    $allotment = HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->room->id,
        'quantity' => 5,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-30',
        'base_rate_override' => 750_000,
        'is_active' => true,
    ]);

    $preview = app(ReservationService::class)->previewSubtotal(
        $this->room,
        Carbon::parse('2026-06-10'),
        Carbon::parse('2026-06-12'),
        1,
        $allotment,
    );

    expect($preview['rate_per_night_avg'])->toBe(750_000.0);
    expect($preview['subtotal'])->toBe(1_500_000.0);
});

test('null base_rate_override falls back to room base rate', function () {
    $allotment = HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->room->id,
        'quantity' => 5,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-30',
        'base_rate_override' => null,
        'is_active' => true,
    ]);

    $preview = app(ReservationService::class)->previewSubtotal(
        $this->room,
        Carbon::parse('2026-06-10'),
        Carbon::parse('2026-06-12'),
        1,
        $allotment,
    );

    expect($preview['rate_per_night_avg'])->toBe(1_000_000.0);
});

test('dynamic pricing period beats base_rate_override on covered nights', function () {
    RoomTypePricingPeriod::create([
        'room_type_id' => $this->room->id,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-30',
        'rate' => 1_300_000,
        'is_active' => true,
    ]);
    $this->room->update(['pricing_type' => 'dynamic']);

    $allotment = HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->room->id,
        'quantity' => 5,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-30',
        'base_rate_override' => 100,
        'is_active' => true,
    ]);

    $preview = app(ReservationService::class)->previewSubtotal(
        $this->room->fresh(),
        Carbon::parse('2026-06-10'),
        Carbon::parse('2026-06-12'),
        1,
        $allotment,
    );

    expect($preview['rate_per_night_avg'])->toBe(1_300_000.0);
});

test('surcharge stacks on top of base_rate_override', function () {
    $allotment = HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->room->id,
        'quantity' => 5,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-30',
        'base_rate_override' => 500_000,
        'surcharge_type' => 'percentage',
        'surcharge_amount' => 10,
        'is_active' => true,
    ]);

    $preview = app(ReservationService::class)->previewSubtotal(
        $this->room,
        Carbon::parse('2026-06-10'),
        Carbon::parse('2026-06-11'),
        1,
        $allotment,
    );

    expect($preview['rate_per_night_avg'])->toBe(550_000.0);
});
