<?php

use App\Enums\PricingType;
use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\HotelEventAllotment;
use App\Models\Project;
use App\Models\RoomType;
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
        'is_active' => true,
    ]);

    $this->cheap = RoomType::factory()->create([
        'hotel_id' => $this->hotel->id,
        'base_rate' => 1_000_000,
        'pricing_type' => PricingType::Flat,
        'is_active' => true,
    ]);
    $this->mid = RoomType::factory()->create([
        'hotel_id' => $this->hotel->id,
        'base_rate' => 1_500_000,
        'pricing_type' => PricingType::Flat,
        'is_active' => true,
    ]);
    $this->expensive = RoomType::factory()->create([
        'hotel_id' => $this->hotel->id,
        'base_rate' => 2_500_000,
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

test('aggregate returns min and max rate across rooms with allotment', function () {
    foreach ([$this->cheap, $this->mid, $this->expensive] as $room) {
        HotelEventAllotment::factory()->create([
            'hotel_id' => $this->hotel->id,
            'room_type_id' => $room->id,
            'quantity' => 5,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-10',
        ]);
    }

    $rows = $this->service->aggregateDailyAvailability(
        $this->hotel,
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-06-02'),
    );

    expect($rows)->toHaveCount(2);
    foreach ($rows as $row) {
        expect($row['min_rate'])->toBe(1_000_000.0)
            ->and($row['max_rate'])->toBe(2_500_000.0)
            ->and($row['total_available'])->toBe(15)
            ->and($row['rooms_count'])->toBe(3);
    }
});

test('aggregate excludes rooms without allotment from totals', function () {
    HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->mid->id,
        'quantity' => 4,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-05',
    ]);

    $rows = $this->service->aggregateDailyAvailability(
        $this->hotel,
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-06-01'),
    );

    expect($rows[0]['min_rate'])->toBe(1_500_000.0)
        ->and($rows[0]['max_rate'])->toBe(1_500_000.0)
        ->and($rows[0]['total_available'])->toBe(4)
        ->and($rows[0]['rooms_count'])->toBe(1);
});

test('aggregate returns null rates and zero availability when no allotment anywhere', function () {
    $rows = $this->service->aggregateDailyAvailability(
        $this->hotel,
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-06-01'),
    );

    expect($rows[0]['min_rate'])->toBeNull()
        ->and($rows[0]['max_rate'])->toBeNull()
        ->and($rows[0]['total_available'])->toBe(0)
        ->and($rows[0]['rooms_count'])->toBe(0);
});

test('aggregate returns empty array when hotel has no active rooms', function () {
    RoomType::query()->where('hotel_id', $this->hotel->id)->update(['is_active' => false]);

    $rows = $this->service->aggregateDailyAvailability(
        $this->hotel,
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-06-03'),
    );

    expect($rows)->toBe([]);
});

test('endpoint returns aggregated data via API', function () {
    HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->cheap->id,
        'quantity' => 10,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-05',
    ]);

    $response = $this->getJson(
        "/api/public/events/{$this->event->slug}/hotels/{$this->hotel->slug}/daily-availability-aggregate?start_date=2026-06-01&end_date=2026-06-02",
        $this->headers
    );

    $response->assertSuccessful();
    $response->assertJsonPath('data.0.min_rate', 1_000_000)
        ->assertJsonPath('data.0.total_available', 10)
        ->assertJsonPath('data.0.rooms_count', 1);
});

test('endpoint validates max range 92 days', function () {
    $response = $this->getJson(
        "/api/public/events/{$this->event->slug}/hotels/{$this->hotel->slug}/daily-availability-aggregate?start_date=2026-01-01&end_date=2026-06-01",
        $this->headers
    );

    $response->assertStatus(422);
});
