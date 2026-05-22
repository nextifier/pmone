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
    ]);
    $this->roomType = RoomType::factory()->create([
        'hotel_id' => $this->hotel->id,
        'base_rate' => 1000000,
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

test('reservation survives when Xendit invoice creation fails', function () {
    $xendit = mock(XenditService::class);
    $xendit->shouldReceive('createCheckout')->andThrow(new RuntimeException('Xendit API down'));
    $this->app->instance(XenditService::class, $xendit);

    $payload = [
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'guest_name' => 'Resilient Guest',
        'guest_email' => 'resilient@test.com',
        'guest_phone' => '0812',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '99',
        'items' => [[
            'room_type_id' => $this->roomType->id,
            'check_in_date' => '2026-06-02',
            'check_out_date' => '2026-06-04',
            'qty' => 1,
        ]],
        'accept_terms' => true,
    ];

    $response = $this->postJson('/api/public/reservations', $payload, $this->headers);

    $response->assertStatus(201);

    $this->assertDatabaseHas('reservations', [
        'guest_email' => 'resilient@test.com',
        'payment_url' => null,
        'xendit_invoice_id' => null,
    ]);

    expect($response->json('data.payment_url'))->toBeNull();
    expect($response->json('data.reservation_number'))->toStartWith('HTL-');
});
