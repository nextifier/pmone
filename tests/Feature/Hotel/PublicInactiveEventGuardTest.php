<?php

use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\HotelEventAllotment;
use App\Models\RoomType;
use App\Services\Xendit\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->consumer = ApiConsumer::create([
        'name' => 'Test',
        'website_url' => 'https://test.com',
        'allowed_origins' => [],
        'rate_limit' => 1000,
        'is_active' => true,
    ]);
    $this->headers = ['X-API-Key' => $this->consumer->api_key];
});

test('public hotel detail returns 404 when event is inactive', function () {
    $event = Event::factory()->create(['slug' => 'past-event', 'is_active' => false]);
    Hotel::factory()->for($event)->create(['slug' => 'any-hotel']);

    $response = $this->getJson('/api/public/events/past-event/hotels/any-hotel', $this->headers);

    $response->assertNotFound();
});

test('public hotel detail still works when event is active', function () {
    $event = Event::factory()->create(['slug' => 'live-event', 'is_active' => true]);
    Hotel::factory()->for($event)->create(['slug' => 'live-hotel']);

    $response = $this->getJson('/api/public/events/live-event/hotels/live-hotel', $this->headers);

    $response->assertSuccessful()
        ->assertJsonPath('data.slug', 'live-hotel');
});

test('public reservation creation fails when hotel event is inactive', function () {
    $event = Event::factory()->create(['is_active' => false]);
    $hotel = Hotel::factory()->for($event)->create(['tax_percentage' => 11]);
    $roomType = RoomType::factory()->create(['hotel_id' => $hotel->id, 'base_rate' => 1000000]);
    HotelEventAllotment::factory()->create([
        'hotel_id' => $hotel->id,
        'room_type_id' => $roomType->id,
        'quantity' => 5,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-10',
        'is_active' => true,
    ]);

    $xendit = mock(XenditService::class);
    $xendit->shouldNotReceive('createInvoice');
    $this->app->instance(XenditService::class, $xendit);

    $response = $this->postJson('/api/public/reservations', [
        'hotel_id' => $hotel->id,
        'guest_name' => 'Test',
        'guest_email' => 'test@example.com',
        'guest_phone' => '0812',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '1',
        'items' => [[
            'room_type_id' => $roomType->id,
            'check_in_date' => '2026-06-02',
            'check_out_date' => '2026-06-05',
            'qty' => 1,
        ]],
        'accept_terms' => true,
    ], $this->headers);

    $response->assertStatus(422);
});
