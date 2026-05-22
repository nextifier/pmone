<?php

use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\RoomType;
use App\Services\Reservation\ReservationService;
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

    $this->project = Project::factory()->create(['status' => 'active']);
    ProjectPaymentGateway::factory()->for($this->project)->default()->create(['mode' => 'test']);
    $this->event = Event::factory()->create(['project_id' => $this->project->id, 'is_active' => true]);
    $this->hotel = Hotel::factory()->withEvent($this->event)->create([
        'tax_percentage' => 11,
        'service_charge_percentage' => 5,
        'is_active' => true,
    ]);
    $this->roomType = RoomType::factory()->create([
        'hotel_id' => $this->hotel->id,
        'base_rate' => 1000000,
        'is_active' => true,
    ]);
});

test('availability returns zero when hotel has no allotment by default', function () {
    $service = app(ReservationService::class);

    $available = $service->checkAvailability(
        $this->event->id,
        $this->hotel->id,
        $this->roomType->id,
        '2026-06-02',
        '2026-06-05'
    );

    expect($available)->toBe(0);
});

test('availability returns unlimited when hotel.settings.allow_unlimited_booking is true', function () {
    $this->hotel->update(['settings' => ['allow_unlimited_booking' => true]]);

    $service = app(ReservationService::class);

    $available = $service->checkAvailability(
        $this->event->id,
        $this->hotel->id,
        $this->roomType->id,
        '2026-06-02',
        '2026-06-05'
    );

    expect($available)->toBe(PHP_INT_MAX);
});

test('public reservation rejected when hotel has no allotment', function () {
    $xendit = mock(XenditService::class);
    $this->app->instance(XenditService::class, $xendit);

    $response = $this->postJson('/api/public/reservations', [
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'guest_name' => 'X',
        'guest_email' => 'x@test.com',
        'guest_phone' => '08',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '1',
        'items' => [[
            'room_type_id' => $this->roomType->id,
            'check_in_date' => '2026-06-02',
            'check_out_date' => '2026-06-05',
            'qty' => 1,
        ]],
        'accept_terms' => true,
    ], $this->headers);

    $response->assertStatus(422);
});

test('public reservation succeeds when allow_unlimited_booking flag set', function () {
    $this->hotel->update(['settings' => ['allow_unlimited_booking' => true]]);

    $xendit = mock(XenditService::class);
    $xendit->shouldReceive('createCheckout')->andReturn([
        'reference' => 'inv_test_unlimited',
        'payment_url' => 'https://checkout.xendit.co/web/inv_test_unlimited',
    ]);
    $this->app->instance(XenditService::class, $xendit);

    $response = $this->postJson('/api/public/reservations', [
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'guest_name' => 'X',
        'guest_email' => 'x@test.com',
        'guest_phone' => '08',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '1',
        'items' => [[
            'room_type_id' => $this->roomType->id,
            'check_in_date' => '2026-06-02',
            'check_out_date' => '2026-06-05',
            'qty' => 1,
        ]],
        'accept_terms' => true,
    ], $this->headers);

    $response->assertStatus(201);
});
