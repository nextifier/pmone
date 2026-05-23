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
        'components_sdk_key' => 'sdk-create-1',
        'payment_gateway_id' => $this->gateway->id,
    ]);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $body['mode'] === 'COMPONENTS'
            && in_array('https://app.test', $body['components_configuration']['origins'], true)
            && $body['allow_save_payment_method'] === 'DISABLED';
    });
});

test('magic-link GET serves the persisted Components SDK key without minting a new session', function () {
    Http::fake();

    $reservation = Reservation::factory()->create([
        'event_id' => $this->event->id,
        'hotel_id' => $this->hotel->id,
        'status' => ReservationStatus::PendingPayment,
        'xendit_invoice_id' => 'ps-stored-session',
        'payment_url' => null,
        'components_sdk_key' => 'sdk-stored-key',
        'payment_gateway_id' => $this->gateway->id,
    ]);
    $rawToken = bin2hex(random_bytes(32));
    $reservation->update(['magic_link_token' => hash('sha256', $rawToken)]);

    $response = $this->getJson("/api/public/reservations/magic/{$rawToken}", $this->headers);

    $response->assertSuccessful()
        ->assertJsonPath('data.components_sdk_key', 'sdk-stored-key');

    // No new session — Xendit Sessions rejects a second create on the same
    // reference_id, so persist-and-serve avoids that whole class of failure.
    Http::assertNothingSent();
});

test('magic-link GET never touches Xendit regardless of gateway type', function () {
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

test('explicit retry-payment mints a fresh Components session and replaces the stored key', function () {
    Http::fake([
        'api.xendit.co/sessions' => Http::response([
            'payment_session_id' => 'ps-retry-fresh',
            'components_sdk_key' => 'sdk-retry-3',
            'status' => 'ACTIVE',
        ], 201),
    ]);

    $reservation = Reservation::factory()->create([
        'event_id' => $this->event->id,
        'hotel_id' => $this->hotel->id,
        'status' => ReservationStatus::PendingPayment,
        'xendit_invoice_id' => 'ps-old-session',
        'payment_url' => null,
        'components_sdk_key' => 'sdk-old-key',
        'payment_gateway_id' => $this->gateway->id,
    ]);
    $rawToken = bin2hex(random_bytes(32));
    $reservation->update(['magic_link_token' => hash('sha256', $rawToken)]);

    $response = $this->postJson(
        "/api/public/reservations/magic/{$rawToken}/retry-payment",
        [],
        $this->headers,
    );

    $response->assertSuccessful()
        ->assertJsonPath('data.components_sdk_key', 'sdk-retry-3');

    $fresh = $reservation->fresh();
    expect($fresh->xendit_invoice_id)->toBe('ps-retry-fresh');
    expect($fresh->components_sdk_key)->toBe('sdk-retry-3');
});
