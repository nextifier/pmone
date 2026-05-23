<?php

use App\Enums\Payment\CheckoutMethod;
use App\Enums\ReservationStatus;
use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\HotelEventAllotment;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\Reservation;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();

    $this->consumer = ApiConsumer::create([
        'name' => 'Test',
        'website_url' => 'https://test.com',
        'allowed_origins' => [],
        'rate_limit' => 1000,
        'is_active' => true,
    ]);
    $this->headers = ['X-API-Key' => $this->consumer->api_key, 'Origin' => 'https://app.test'];

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

    $this->gateway = ProjectPaymentGateway::factory()->for($this->project)->create([
        'mode' => 'test',
        'is_active' => true,
        'checkout_method' => CheckoutMethod::SessionsComponents,
    ]);
});

test('public reservation POST with a Components gateway returns the SDK key and stores a ps- session id', function () {
    Http::fake([
        'api.xendit.co/sessions' => Http::response([
            'payment_session_id' => 'ps-create-test',
            'components_sdk_key' => 'sdk-create-1',
            'status' => 'ACTIVE',
        ], 201),
    ]);

    $response = $this->postJson('/api/public/reservations', [
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'guest_name' => 'Components Guest',
        'guest_email' => 'components@test.com',
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

    $response->assertStatus(201)
        ->assertJsonPath('data.components_sdk_key', 'sdk-create-1')
        ->assertJsonPath('data.payment_url', null);

    $this->assertDatabaseHas('reservations', [
        'guest_email' => 'components@test.com',
        'xendit_invoice_id' => 'ps-create-test',
        'payment_url' => null,
        'payment_gateway_id' => $this->gateway->id,
    ]);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $body['mode'] === 'COMPONENTS'
            && in_array('https://app.test', $body['components_configuration']['origins'], true)
            && $body['allow_save_payment_method'] === 'DISABLED';
    });
});

test('magic-link GET on a pending Components reservation regenerates the session and exposes the new SDK key', function () {
    $reservation = Reservation::factory()->create([
        'event_id' => $this->event->id,
        'hotel_id' => $this->hotel->id,
        'status' => ReservationStatus::PendingPayment,
        'xendit_invoice_id' => 'ps-stale-session',
        'payment_url' => null,
        'payment_gateway_id' => $this->gateway->id,
    ]);
    // Reservation factory hashes a random token; mint a fresh raw token whose
    // hash we know so we can pass it through the public endpoint.
    $rawToken = bin2hex(random_bytes(32));
    $reservation->update(['magic_link_token' => hash('sha256', $rawToken)]);

    Http::fake([
        'api.xendit.co/sessions' => Http::response([
            'payment_session_id' => 'ps-refreshed',
            'components_sdk_key' => 'sdk-refreshed-2',
            'status' => 'ACTIVE',
        ], 201),
    ]);

    $response = $this->getJson("/api/public/reservations/magic/{$rawToken}", $this->headers);

    $response->assertSuccessful()
        ->assertJsonPath('data.components_sdk_key', 'sdk-refreshed-2');

    expect($reservation->fresh()->xendit_invoice_id)->toBe('ps-refreshed');
});

test('magic-link GET on a non-Components pending reservation does not touch Xendit', function () {
    Http::fake();

    $legacyGateway = ProjectPaymentGateway::factory()->for($this->project)->create([
        'mode' => 'test',
        'label' => 'legacy-on-pending',
        'is_active' => true,
        'checkout_method' => CheckoutMethod::PaymentLinkLegacy,
    ]);

    $reservation = Reservation::factory()->create([
        'event_id' => $this->event->id,
        'hotel_id' => $this->hotel->id,
        'status' => ReservationStatus::PendingPayment,
        'xendit_invoice_id' => 'inv_legacy',
        'payment_url' => 'https://checkout.xendit.co/web/inv_legacy',
        'payment_gateway_id' => $legacyGateway->id,
    ]);
    $rawToken = bin2hex(random_bytes(32));
    $reservation->update(['magic_link_token' => hash('sha256', $rawToken)]);

    $this->getJson("/api/public/reservations/magic/{$rawToken}", $this->headers)
        ->assertSuccessful();

    Http::assertNothingSent();
});
