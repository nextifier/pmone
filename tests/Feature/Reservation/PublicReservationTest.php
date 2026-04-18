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
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('xendit.webhook_token', 'test-token');
    config()->set('app.url', 'http://pmone.test');

    $this->consumer = ApiConsumer::create([
        'name' => 'Test',
        'website_url' => 'https://test.com',
        'allowed_origins' => [],
        'rate_limit' => 1000,
        'is_active' => true,
    ]);

    $this->headers = ['X-API-Key' => $this->consumer->api_key];

    $this->project = Project::factory()->create(['status' => 'active']);
    $this->event = Event::factory()->create(['project_id' => $this->project->id, 'is_active' => true]);
    $this->hotel = Hotel::factory()->for($this->event)->create(['tax_percentage' => 11, 'service_charge_percentage' => 5]);
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

test('public can list active hotels', function () {
    $response = $this->getJson('/api/public/hotels', $this->headers);

    $response->assertSuccessful();
    expect(count($response->json('data')))->toBeGreaterThanOrEqual(1);
});

test('public can show hotel by event+hotel slug', function () {
    $response = $this->getJson("/api/public/events/{$this->event->slug}/hotels/{$this->hotel->slug}", $this->headers);

    $response->assertSuccessful()
        ->assertJsonPath('data.slug', $this->hotel->slug);
});

test('public availability check returns available count', function () {
    $response = $this->postJson('/api/public/hotels/availability', [
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->roomType->id,
        'check_in_date' => '2026-06-02',
        'check_out_date' => '2026-06-04',
        'qty' => 2,
    ], $this->headers);

    $response->assertSuccessful()
        ->assertJsonPath('data.available', 5)
        ->assertJsonPath('data.is_available', true);
});

test('public reservation creation creates pending_payment reservation', function () {
    Queue::fake();

    $xendit = mock(XenditService::class);
    $xendit->shouldReceive('createInvoice')->andReturn([
        'invoice_id' => 'inv_test_123',
        'invoice_url' => 'https://checkout.xendit.co/web/inv_test_123',
    ]);
    $this->app->instance(XenditService::class, $xendit);

    $payload = [
        'hotel_id' => $this->hotel->id,
        'guest_name' => 'John Doe',
        'guest_email' => 'john@example.com',
        'guest_phone' => '+62812345678',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '3201010101010001',
        'guest_nationality' => 'Indonesia',
        'items' => [[
            'room_type_id' => $this->roomType->id,
            'check_in_date' => '2026-06-02',
            'check_out_date' => '2026-06-05',
            'qty' => 1,
        ]],
        'accept_terms' => true,
    ];

    $response = $this->postJson('/api/public/reservations', $payload, $this->headers);

    $response->assertStatus(201)
        ->assertJsonPath('data.status', 'pending_payment');

    expect($response->json('data.reservation_number'))->toStartWith('HTL-');
    expect($response->json('data.magic_link_token'))->not->toBeNull();
    expect($response->json('data.payment_url'))->toContain('xendit.co');

    $this->assertDatabaseHas('reservations', [
        'guest_email' => 'john@example.com',
        'xendit_invoice_id' => 'inv_test_123',
        'event_id' => $this->event->id,
    ]);
});

test('reservation requires accept_terms', function () {
    $response = $this->postJson('/api/public/reservations', [
        'hotel_id' => $this->hotel->id,
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
    ], $this->headers);

    $response->assertStatus(422);
});

test('reservation fails when no allotment available', function () {
    HotelEventAllotment::query()->update(['quantity' => 0]);

    $xendit = mock(XenditService::class);
    $xendit->shouldReceive('createInvoice')->andReturn(['invoice_id' => 'x', 'invoice_url' => 'x']);
    $this->app->instance(XenditService::class, $xendit);

    $response = $this->postJson('/api/public/reservations', [
        'hotel_id' => $this->hotel->id,
        'guest_name' => 'X',
        'guest_email' => 'x@test.com',
        'guest_phone' => '08',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '1',
        'items' => [[
            'room_type_id' => $this->roomType->id,
            'check_in_date' => '2026-06-02',
            'check_out_date' => '2026-06-05',
            'qty' => 3,
        ]],
        'accept_terms' => true,
    ], $this->headers);

    $response->assertStatus(422);
});

test('availability decreases after reservation is created', function () {
    Queue::fake();
    $xendit = mock(XenditService::class);
    $xendit->shouldReceive('createInvoice')->andReturn(['invoice_id' => 'inv_x', 'invoice_url' => 'x']);
    $this->app->instance(XenditService::class, $xendit);

    $service = app(ReservationService::class);
    $service->createReservation([
        'hotel_id' => $this->hotel->id,
        'guest_name' => 'A',
        'guest_email' => 'a@test.com',
        'guest_phone' => '0',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '1',
        'items' => [[
            'room_type_id' => $this->roomType->id,
            'check_in_date' => '2026-06-02',
            'check_out_date' => '2026-06-05',
            'qty' => 3,
        ]],
    ]);

    $available = $service->checkAvailability($this->event->id, $this->hotel->id, $this->roomType->id, '2026-06-02', '2026-06-05');

    expect($available)->toBe(2);
});
