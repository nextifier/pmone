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
    config()->set('xendit.webhook_token', 'test-token');

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
        'service_charge_percentage' => 0,
    ]);
    $this->roomType = RoomType::factory()->create([
        'hotel_id' => $this->hotel->id,
        'base_rate' => 500000,
    ]);
    HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->roomType->id,
        'quantity' => 5,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-10',
        'is_active' => true,
    ]);
});

test('public reservation succeeds without guest_address field', function () {
    Queue::fake();

    $xendit = mock(XenditService::class);
    $xendit->shouldReceive('createCheckout')->andReturn([
        'reference' => 'inv_noaddr',
        'payment_url' => 'https://checkout.xendit.co/web/inv_noaddr',
    ]);
    $xendit->shouldReceive('gateway')->andReturnNull();
    $this->app->instance(XenditService::class, $xendit);

    $response = $this->postJson('/api/public/reservations', [
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'guest_name' => 'No Address',
        'guest_email' => 'no-addr@test.com',
        'guest_phone' => '+62812000000',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '3201010101010011',
        'items' => [[
            'room_type_id' => $this->roomType->id,
            'check_in_date' => '2026-06-02',
            'check_out_date' => '2026-06-04',
            'qty' => 1,
        ]],
        'accept_terms' => true,
    ], $this->headers);

    $response->assertStatus(201)
        ->assertJsonPath('data.status', 'pending_payment');

    $this->assertDatabaseHas('reservations', [
        'guest_email' => 'no-addr@test.com',
    ]);
});

test('reservation payload ignores guest_address even when provided', function () {
    Queue::fake();

    $xendit = mock(XenditService::class);
    $xendit->shouldReceive('createCheckout')->andReturn([
        'reference' => 'inv_ignore',
        'payment_url' => 'https://checkout.xendit.co/web/inv_ignore',
    ]);
    $xendit->shouldReceive('gateway')->andReturnNull();
    $this->app->instance(XenditService::class, $xendit);

    $response = $this->postJson('/api/public/reservations', [
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'guest_name' => 'Legacy Addr',
        'guest_email' => 'legacy-addr@test.com',
        'guest_phone' => '+62812000000',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '3201010101010012',
        'guest_address' => 'Jl. Example 123',
        'items' => [[
            'room_type_id' => $this->roomType->id,
            'check_in_date' => '2026-06-02',
            'check_out_date' => '2026-06-04',
            'qty' => 1,
        ]],
        'accept_terms' => true,
    ], $this->headers);

    $response->assertStatus(201);
});
