<?php

use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\HotelEventAllotment;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\RoomType;
use App\Services\Xendit\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

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
    $this->event = Event::factory()->withoutPaymentGateway()->create([
        'project_id' => $this->project->id,
        'is_active' => true,
    ]);
    $this->hotel = Hotel::factory()->withEvent($this->event)->create();
    $this->roomType = RoomType::factory()->create(['hotel_id' => $this->hotel->id, 'base_rate' => 1000000]);
    HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->roomType->id,
        'quantity' => 5,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-10',
        'is_active' => true,
    ]);
});

test('reservation creation returns 404 when project has no active xendit gateway', function () {
    Queue::fake();

    // Middleware blocks the public reservation route before controller runs
    // when the event's project has no active payment gateway; the response
    // is identical to "feature disabled" to avoid leaking configuration
    // state to public consumers.
    $response = $this->postJson('/api/public/reservations', [
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'guest_name' => 'No Gateway',
        'guest_email' => 'nogw@test.com',
        'guest_phone' => '08',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '1',
        'items' => [[
            'room_type_id' => $this->roomType->id,
            'check_in_date' => '2026-06-02',
            'check_out_date' => '2026-06-04',
            'qty' => 1,
        ]],
        'accept_terms' => true,
    ], $this->headers);

    $response->assertStatus(404)
        ->assertJsonPath('error_code', 'HOTEL_RESERVATION_DISABLED');
});

test('reservation creation persists payment_gateway_id when xendit invoice succeeds', function () {
    Queue::fake();

    $gateway = ProjectPaymentGateway::factory()->for($this->project)->default()->create([
        'mode' => 'test',
    ]);

    $xendit = mock(XenditService::class);
    $xendit->shouldReceive('createCheckout')->andReturn([
        'reference' => 'inv_persist_test',
        'payment_url' => 'https://checkout.xendit.co/web/inv_persist_test',
    ]);
    $xendit->shouldReceive('gateway')->andReturn($gateway);
    $this->app->instance(XenditService::class, $xendit);

    $response = $this->postJson('/api/public/reservations', [
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'guest_name' => 'With Gateway',
        'guest_email' => 'withgw@test.com',
        'guest_phone' => '08',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '1',
        'items' => [[
            'room_type_id' => $this->roomType->id,
            'check_in_date' => '2026-06-02',
            'check_out_date' => '2026-06-04',
            'qty' => 1,
        ]],
        'accept_terms' => true,
    ], $this->headers);

    $response->assertStatus(201);

    $this->assertDatabaseHas('reservations', [
        'guest_email' => 'withgw@test.com',
        'xendit_invoice_id' => 'inv_persist_test',
        'payment_gateway_id' => $gateway->id,
    ]);
});

test('xenditFor uses test gateway when APP_ENV is testing', function () {
    Queue::fake();

    $liveGateway = ProjectPaymentGateway::factory()->for($this->project)->default()->create([
        'mode' => 'live',
    ]);
    $testGateway = ProjectPaymentGateway::factory()->for($this->project)->create([
        'mode' => 'test',
        'is_active' => true,
    ]);

    $xendit = mock(XenditService::class);
    $xendit->shouldReceive('createCheckout')->andReturn([
        'reference' => 'inv_test_mode',
        'payment_url' => 'https://x',
    ]);
    $xendit->shouldReceive('gateway')->andReturn($testGateway);
    $this->app->instance(XenditService::class, $xendit);

    $response = $this->postJson('/api/public/reservations', [
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'guest_name' => 'Test Mode',
        'guest_email' => 'testmode@test.com',
        'guest_phone' => '08',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '1',
        'items' => [[
            'room_type_id' => $this->roomType->id,
            'check_in_date' => '2026-06-02',
            'check_out_date' => '2026-06-04',
            'qty' => 1,
        ]],
        'accept_terms' => true,
    ], $this->headers);

    $response->assertStatus(201);

    $this->assertDatabaseHas('reservations', [
        'guest_email' => 'testmode@test.com',
        'payment_gateway_id' => $testGateway->id,
    ]);
});
