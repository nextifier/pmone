<?php

use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\HotelEventAllotment;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    // Dynamic dates so the fixtures never drift into the past.
    $this->checkIn = now()->addDays(10)->toDateString();
    $this->checkOut = now()->addDays(12)->toDateString();

    HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->roomType->id,
        'quantity' => 5,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(20)->toDateString(),
        'is_active' => true,
    ]);
});

function bookMidtrans($ctx, array $overrides = [])
{
    return $ctx->postJson('/api/public/reservations', array_merge([
        'hotel_id' => $ctx->hotel->id,
        'event_id' => $ctx->event->id,
        'guest_name' => 'Midtrans Guest',
        'guest_email' => 'mid@test.com',
        'guest_phone' => '08123',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '1',
        'items' => [[
            'room_type_id' => $ctx->roomType->id,
            'check_in_date' => $ctx->checkIn,
            'check_out_date' => $ctx->checkOut,
            'qty' => 1,
        ]],
        'accept_terms' => true,
    ], $overrides), $ctx->headers);
}

test('reservation routes to midtrans Snap and persists provider fields', function () {
    Queue::fake();
    Http::fake([
        'app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
            'token' => 'snap-tok-9',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-tok-9',
        ], 201),
    ]);

    $gateway = ProjectPaymentGateway::factory()->for($this->project)->midtrans()->create(['is_active' => true]);

    $response = bookMidtrans($this);

    $response->assertStatus(201)
        ->assertJsonPath('data.payment_url', 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-tok-9');

    $this->assertDatabaseHas('reservations', [
        'guest_email' => 'mid@test.com',
        'xendit_invoice_id' => 'snap-tok-9',
        'payment_method' => 'midtrans',
        'payment_gateway_id' => $gateway->id,
    ]);
});

test('midtrans Snap failure keeps the reservation for retry', function () {
    Queue::fake();
    Http::fake([
        'app.sandbox.midtrans.com/snap/v1/transactions' => Http::response(['status_code' => '500'], 500),
    ]);

    ProjectPaymentGateway::factory()->for($this->project)->midtrans()->create(['is_active' => true]);

    $response = bookMidtrans($this, ['guest_email' => 'resilient-mid@test.com']);

    $response->assertStatus(201);
    $this->assertDatabaseHas('reservations', [
        'guest_email' => 'resilient-mid@test.com',
        'payment_url' => null,
        'xendit_invoice_id' => null,
    ]);
});

test('booking stores the originating origin and points Snap finish at the bouncer', function () {
    Queue::fake();
    Http::fake([
        'app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
            'token' => 'snap-tok-origin',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-tok-origin',
        ], 201),
    ]);

    ProjectPaymentGateway::factory()->for($this->project)->midtrans()->create(['is_active' => true]);

    bookMidtrans($this, ['origin' => 'https://iicc.askindo.id', 'guest_email' => 'origin@test.com'])
        ->assertStatus(201);

    // Both providers route through the bouncer; the originating domain is stored
    // on the reservation for the bouncer to resolve at redirect time.
    $bouncer = rtrim(config('app.url'), '/').'/payment/redirect';
    Http::assertSent(fn ($request) => str_starts_with(
        (string) ($request->data()['callbacks']['finish'] ?? ''),
        $bouncer
    ));

    $this->assertDatabaseHas('reservations', [
        'guest_email' => 'origin@test.com',
        'return_origin' => 'https://iicc.askindo.id',
    ]);
});

test('an untrusted origin falls back to the global frontend url for return_origin', function () {
    Queue::fake();
    Http::fake([
        'app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
            'token' => 'snap-tok-evil',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/x',
        ], 201),
    ]);

    ProjectPaymentGateway::factory()->for($this->project)->midtrans()->create(['is_active' => true]);

    bookMidtrans($this, ['origin' => 'https://evil.example.com', 'guest_email' => 'evil@test.com'])
        ->assertStatus(201);

    $this->assertDatabaseHas('reservations', [
        'guest_email' => 'evil@test.com',
        'return_origin' => rtrim(config('app.frontend_url'), '/'),
    ]);
});
