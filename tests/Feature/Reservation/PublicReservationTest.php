<?php

use App\Enums\PricingType;
use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\HotelEventAllotment;
use App\Models\HotelTransferOption;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\Reservation;
use App\Models\RoomType;
use App\Models\RoomTypePricingPeriod;
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
    ProjectPaymentGateway::factory()->for($this->project)->default()->create(['mode' => 'test']);
    $this->event = Event::factory()->create(['project_id' => $this->project->id, 'is_active' => true]);
    $this->hotel = Hotel::factory()->withEvent($this->event)->create(['tax_percentage' => 11, 'service_charge_percentage' => 5]);
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
        'event_id' => $this->event->id,
        'event_slug' => $this->event->slug,
        'room_type_id' => $this->roomType->id,
        'check_in_date' => '2026-06-02',
        'check_out_date' => '2026-06-04',
        'qty' => 2,
    ], $this->headers);

    $response->assertSuccessful()
        ->assertJsonPath('data.available', 5)
        ->assertJsonPath('data.is_available', true);
});

test('public availability returns subtotal for dynamic pricing room', function () {
    $dynRoom = RoomType::factory()->create([
        'hotel_id' => $this->hotel->id,
        'base_rate' => 1000000,
        'pricing_type' => PricingType::Dynamic,
    ]);
    RoomTypePricingPeriod::factory()->for($dynRoom)->create([
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-03',
        'rate' => 1500000,
    ]);
    RoomTypePricingPeriod::factory()->for($dynRoom)->create([
        'start_date' => '2026-06-04',
        'end_date' => '2026-06-10',
        'rate' => 1800000,
    ]);
    HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $dynRoom->id,
        'quantity' => 5,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-10',
        'is_active' => true,
    ]);

    // 2 nights: Jun 2 (1.5M) + Jun 3 (1.5M) = 3M for 1 room
    $response = $this->postJson('/api/public/hotels/availability', [
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'event_slug' => $this->event->slug,
        'room_type_id' => $dynRoom->id,
        'check_in_date' => '2026-06-02',
        'check_out_date' => '2026-06-04',
        'qty' => 1,
    ], $this->headers);

    $response->assertSuccessful()
        ->assertJsonPath('data.pricing_type', 'dynamic');
    expect((float) $response->json('data.subtotal'))->toBe(3000000.0);
    expect($response->json('data.daily_breakdown'))->toHaveCount(2);
});

test('public booking creates reservation with correct dynamic subtotal', function () {
    Queue::fake();

    $dynRoom = RoomType::factory()->create([
        'hotel_id' => $this->hotel->id,
        'base_rate' => 1000000,
        'pricing_type' => PricingType::Dynamic,
    ]);
    RoomTypePricingPeriod::factory()->for($dynRoom)->create([
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-10',
        'rate' => 1500000,
    ]);
    HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $dynRoom->id,
        'quantity' => 5,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-10',
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/public/reservations', [
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'guest_name' => 'Dyn Guest',
        'guest_email' => 'dyn@test.com',
        'guest_phone' => '08123',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '12345',
        'items' => [[
            'room_type_id' => $dynRoom->id,
            'check_in_date' => '2026-06-02',
            'check_out_date' => '2026-06-04',
            'qty' => 1,
        ]],
        'accept_terms' => true,
    ], $this->headers);

    $response->assertSuccessful();
    $reservationNumber = $response->json('data.reservation_number');

    $reservation = Reservation::query()
        ->where('reservation_number', $reservationNumber)
        ->with('items')
        ->firstOrFail();

    expect((float) $reservation->subtotal_rooms)->toBe(3000000.0);
    expect($reservation->items->first()->daily_breakdown)->toHaveCount(2);
});

test('public booking rejects missing-night case for dynamic pricing', function () {
    Queue::fake();

    $dynRoom = RoomType::factory()->create([
        'hotel_id' => $this->hotel->id,
        'base_rate' => 1000000,
        'pricing_type' => PricingType::Dynamic,
    ]);
    RoomTypePricingPeriod::factory()->for($dynRoom)->create([
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-03',
        'rate' => 1500000,
    ]);
    HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $dynRoom->id,
        'quantity' => 5,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-10',
        'is_active' => true,
    ]);

    // Jun 2-6 includes Jun 4 + Jun 5 which have no pricing period
    $response = $this->postJson('/api/public/reservations', [
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'guest_name' => 'Gap Guest',
        'guest_email' => 'gap@test.com',
        'guest_phone' => '08123',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '12345',
        'items' => [[
            'room_type_id' => $dynRoom->id,
            'check_in_date' => '2026-06-02',
            'check_out_date' => '2026-06-06',
            'qty' => 1,
        ]],
        'accept_terms' => true,
    ], $this->headers);

    $response->assertStatus(422);
});

test('public reservation creation creates pending_payment reservation', function () {
    Queue::fake();

    $xendit = mock(XenditService::class);
    $xendit->shouldReceive('createCheckout')->andReturn([
        'reference' => 'inv_test_123',
        'payment_url' => 'https://checkout.xendit.co/web/inv_test_123',
    ]);
    $xendit->shouldReceive('gateway')->andReturnNull();
    $this->app->instance(XenditService::class, $xendit);

    $payload = [
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
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
    ], $this->headers);

    $response->assertStatus(422);
});

test('reservation fails when no allotment available', function () {
    HotelEventAllotment::query()->update(['quantity' => 0]);

    $xendit = mock(XenditService::class);
    $xendit->shouldReceive('createCheckout')->andReturn(['reference' => 'x', 'payment_url' => 'x']);
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
            'qty' => 3,
        ]],
        'accept_terms' => true,
    ], $this->headers);

    $response->assertStatus(422);
});

test('availability decreases after reservation is created', function () {
    Queue::fake();
    $xendit = mock(XenditService::class);
    $xendit->shouldReceive('createCheckout')->andReturn(['reference' => 'inv_x', 'payment_url' => 'x']);
    $xendit->shouldReceive('gateway')->andReturnNull();
    $this->app->instance(XenditService::class, $xendit);

    $service = app(ReservationService::class);
    $service->createReservation([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
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

test('transfer price is server-resolved (T5: client price tampering ignored)', function () {
    Queue::fake();

    $option = HotelTransferOption::factory()->for($this->hotel)->create([
        'price' => 500000,
        'is_active' => true,
    ]);

    $xendit = mock(XenditService::class);
    $xendit->shouldReceive('createCheckout')->andReturn([
        'reference' => 'inv_t5',
        'payment_url' => 'https://checkout.xendit.co/web/inv_t5',
    ]);
    $xendit->shouldReceive('gateway')->andReturnNull();
    $this->app->instance(XenditService::class, $xendit);

    $payload = [
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'guest_name' => 'Tamper Test',
        'guest_email' => 'tamper@test.com',
        'guest_phone' => '+62812345678',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '3201010101010001',
        'items' => [[
            'room_type_id' => $this->roomType->id,
            'check_in_date' => '2026-06-02',
            'check_out_date' => '2026-06-05',
            'qty' => 1,
        ]],
        'transfers' => [[
            'transfer_option_id' => $option->id,
            'direction' => 'in',
            'transfer_date' => '2026-06-02',
            'pax_count' => 2,
            // Client tampers price to zero — must be ignored
            'price' => 0,
        ]],
        'accept_terms' => true,
    ];

    $response = $this->postJson('/api/public/reservations', $payload, $this->headers);
    $response->assertStatus(201);

    $this->assertDatabaseHas('reservation_transfers', [
        'transfer_option_id' => $option->id,
        'price' => 500000, // server price, not client 0
    ]);
});

test('transfer rejected when option belongs to different hotel (C2)', function () {
    Queue::fake();

    $otherHotel = Hotel::factory()->create();
    $foreignOption = HotelTransferOption::factory()->for($otherHotel)->create(['is_active' => true]);

    $xendit = mock(XenditService::class);
    $xendit->shouldReceive('createCheckout')->andReturn(['reference' => 'x', 'payment_url' => 'x']);
    $xendit->shouldReceive('gateway')->andReturnNull();
    $this->app->instance(XenditService::class, $xendit);

    $payload = [
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'guest_name' => 'Cross Hotel Test',
        'guest_email' => 'cross@test.com',
        'guest_phone' => '+62812345678',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '3201010101010001',
        'items' => [[
            'room_type_id' => $this->roomType->id,
            'check_in_date' => '2026-06-02',
            'check_out_date' => '2026-06-05',
            'qty' => 1,
        ]],
        'transfers' => [[
            'transfer_option_id' => $foreignOption->id, // belongs to otherHotel
            'direction' => 'in',
            'transfer_date' => '2026-06-02',
            'pax_count' => 2,
        ]],
        'accept_terms' => true,
    ];

    // StorePublicReservationRequest::withValidator likely catches this first;
    // either way, no reservation must be created.
    $response = $this->postJson('/api/public/reservations', $payload, $this->headers);
    expect($response->status())->toBeIn([422]);
    $this->assertDatabaseMissing('reservation_transfers', [
        'transfer_option_id' => $foreignOption->id,
    ]);
});
