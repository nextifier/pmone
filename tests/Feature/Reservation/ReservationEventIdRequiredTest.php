<?php

use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\HotelEventAllotment;
use App\Models\Project;
use App\Models\RoomType;
use App\Services\Reservation\ReservationService;
use App\Services\Xendit\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->consumer = ApiConsumer::factory()->create([
        'api_key' => 'test-key',
        'is_active' => true,
    ]);
    $this->headers = ['X-API-Key' => $this->consumer->api_key];

    $this->project = Project::factory()->create(['status' => 'active']);
    $this->event = Event::factory()->create(['project_id' => $this->project->id, 'is_active' => true]);
    $this->otherEvent = Event::factory()->create(['project_id' => $this->project->id, 'is_active' => true]);

    $this->hotel = Hotel::factory()->withEvent($this->event)->create();
    $this->roomType = RoomType::factory()->create([
        'hotel_id' => $this->hotel->id,
        'base_rate' => 500_000,
    ]);
    HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->roomType->id,
        'quantity' => 5,
        'start_date' => now()->addDays(10)->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
        'is_active' => true,
    ]);
});

test('public reservation rejects payload without event_id', function () {
    $response = $this->postJson('/api/public/reservations', [
        'hotel_id' => $this->hotel->id,
        'guest_name' => 'Anon',
        'guest_email' => 'anon@test.com',
        'guest_phone' => '0812',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '1',
        'items' => [[
            'room_type_id' => $this->roomType->id,
            'check_in_date' => now()->addDays(12)->toDateString(),
            'check_out_date' => now()->addDays(14)->toDateString(),
            'qty' => 1,
        ]],
        'accept_terms' => true,
    ], $this->headers);

    $response->assertStatus(422)->assertJsonValidationErrors(['event_id']);
});

test('reservation rejects event that hotel is not attached to', function () {
    $response = $this->postJson('/api/public/reservations', [
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->otherEvent->id,
        'guest_name' => 'Anon',
        'guest_email' => 'anon@test.com',
        'guest_phone' => '0812',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '1',
        'items' => [[
            'room_type_id' => $this->roomType->id,
            'check_in_date' => now()->addDays(12)->toDateString(),
            'check_out_date' => now()->addDays(14)->toDateString(),
            'qty' => 1,
        ]],
        'accept_terms' => true,
    ], $this->headers);

    $response->assertStatus(422);
});

test('service createReservation requires explicit event_id', function () {
    $service = app(ReservationService::class);
    expect(fn () => $service->createReservation([
        'hotel_id' => $this->hotel->id,
        'guest_name' => 'Anon',
        'guest_email' => 'anon@test.com',
        'guest_phone' => '0812',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '1',
        'items' => [[
            'room_type_id' => $this->roomType->id,
            'check_in_date' => now()->addDays(12)->toDateString(),
            'check_out_date' => now()->addDays(14)->toDateString(),
            'qty' => 1,
        ]],
    ]))->toThrow(HttpException::class);
});

test('service createReservation succeeds when event is active and attached', function () {
    $xendit = mock(XenditService::class);
    $xendit->shouldReceive('createCheckout')->andReturn([
        'reference' => 'inv-success',
        'payment_url' => 'https://example/inv',
    ]);
    $xendit->shouldReceive('gateway')->andReturn(null);
    $this->app->instance(XenditService::class, $xendit);

    $service = app(ReservationService::class);
    $reservation = $service->createReservation([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'guest_name' => 'Anon',
        'guest_email' => 'anon@test.com',
        'guest_phone' => '0812',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '1',
        'items' => [[
            'room_type_id' => $this->roomType->id,
            'check_in_date' => now()->addDays(12)->toDateString(),
            'check_out_date' => now()->addDays(14)->toDateString(),
            'qty' => 1,
        ]],
    ], $xendit);

    expect($reservation->event_id)->toBe($this->event->id);
    expect($reservation->hotel_id)->toBe($this->hotel->id);
});
