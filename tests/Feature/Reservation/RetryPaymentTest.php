<?php

use App\Enums\ReservationStatus;
use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\HotelEventAllotment;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\Reservation;
use App\Models\ReservationItem;
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
    $this->event = Event::factory()->withoutPaymentGateway()->create([
        'project_id' => $this->project->id,
        'is_active' => true,
    ]);
    $this->hotel = Hotel::factory()->withEvent($this->event)->create();
    $this->roomType = RoomType::factory()->create(['hotel_id' => $this->hotel->id, 'base_rate' => 1_000_000]);
    HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->roomType->id,
        'quantity' => 5,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-10',
        'is_active' => true,
    ]);
});

function makePendingReservation(array $overrides = []): array
{
    $rawToken = 'raw-test-token-'.bin2hex(random_bytes(8));
    $hashedToken = hash('sha256', $rawToken);

    $reservation = Reservation::factory()->create(array_merge([
        'event_id' => test()->event->id,
        'hotel_id' => test()->hotel->id,
        'status' => ReservationStatus::PendingPayment,
        'payment_url' => null,
        'xendit_invoice_id' => null,
        'magic_link_token' => $hashedToken,
        'magic_link_expires_at' => now()->addDay(),
    ], $overrides));

    ReservationItem::factory()->create([
        'reservation_id' => $reservation->id,
        'room_type_id' => test()->roomType->id,
        'check_in_date' => '2026-06-04',
        'check_out_date' => '2026-06-07',
        'qty' => 1,
    ]);

    return [$reservation, $rawToken];
}

test('retry returns 422 when reservation is already paid', function () {
    [, $rawToken] = makePendingReservation(['status' => ReservationStatus::Paid, 'paid_at' => now()]);

    $this->postJson("/api/public/reservations/magic/{$rawToken}/retry-payment", [], $this->headers)
        ->assertStatus(422)
        ->assertJsonPath('message', 'Only pending payments can be retried.');
});

test('retry returns 422 when project has no active payment gateway', function () {
    [, $rawToken] = makePendingReservation();

    $this->postJson("/api/public/reservations/magic/{$rawToken}/retry-payment", [], $this->headers)
        ->assertStatus(422);
});

test('retry regenerates payment_url when gateway is active', function () {
    $gateway = ProjectPaymentGateway::factory()->for($this->project)->default()->create(['mode' => 'test']);
    [$reservation, $rawToken] = makePendingReservation();

    $xendit = mock(XenditService::class);
    $xendit->shouldReceive('gateway')->andReturn($gateway);
    $xendit->shouldReceive('createInvoice')->once()->andReturn([
        'invoice_id' => 'inv-retry-success',
        'invoice_url' => 'https://checkout.xendit.co/web/retry-ok',
    ]);
    $this->app->instance(XenditService::class, $xendit);

    $this->postJson("/api/public/reservations/magic/{$rawToken}/retry-payment", [], $this->headers)
        ->assertSuccessful()
        ->assertJsonPath('message', 'Payment link regenerated.');

    $fresh = $reservation->fresh();
    expect($fresh->payment_url)->toBe('https://checkout.xendit.co/web/retry-ok');
    expect($fresh->xendit_invoice_id)->toBe('inv-retry-success');
});

test('retry returns 404 when magic link is invalid', function () {
    $this->postJson('/api/public/reservations/magic/this-is-not-a-real-token/retry-payment', [], $this->headers)
        ->assertStatus(404);
});

test('retry returns 410 when magic link has expired', function () {
    [, $rawToken] = makePendingReservation(['magic_link_expires_at' => now()->subDay()]);

    $this->postJson("/api/public/reservations/magic/{$rawToken}/retry-payment", [], $this->headers)
        ->assertStatus(410);
});

test('retry returns 503 when Xendit invoice creation throws', function () {
    ProjectPaymentGateway::factory()->for($this->project)->default()->create(['mode' => 'test']);
    [, $rawToken] = makePendingReservation();

    $xendit = mock(XenditService::class);
    $xendit->shouldReceive('gateway')->andReturn((object) ['id' => 1]);
    $xendit->shouldReceive('createInvoice')->andThrow(new RuntimeException('Xendit API down'));
    $this->app->instance(XenditService::class, $xendit);

    $this->postJson("/api/public/reservations/magic/{$rawToken}/retry-payment", [], $this->headers)
        ->assertStatus(503);
});

test('retry requires API key', function () {
    [, $rawToken] = makePendingReservation();

    $this->postJson("/api/public/reservations/magic/{$rawToken}/retry-payment")
        ->assertStatus(401);
});
