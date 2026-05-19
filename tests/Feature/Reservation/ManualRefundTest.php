<?php

use App\Enums\ReservationStatus;
use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\Project;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    ApiConsumer::create([
        'name' => 'Test',
        'website_url' => 'https://test.com',
        'allowed_origins' => [],
        'rate_limit' => 1000,
        'is_active' => true,
    ]);

    foreach (['reservations.read', 'reservations.refund'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->givePermissionTo(['reservations.read', 'reservations.refund']);
    $this->actingAs($this->admin);

    $this->project = Project::factory()->create(['status' => 'active']);
    $this->event = Event::factory()->create(['project_id' => $this->project->id, 'is_active' => true]);
    $this->hotel = Hotel::factory()->withEvent($this->event)->create();
});

function makeCancelledReservation(array $overrides = []): Reservation
{
    return Reservation::factory()->paid()->create(array_merge([
        'hotel_id' => test()->hotel->id,
        'event_id' => test()->event->id,
        'status' => ReservationStatus::Cancelled,
        'cancelled_at' => now(),
        'refund_amount' => 1000000,
        'refund_reason' => 'Guest requested',
        'xendit_invoice_id' => 'inv_'.Str::random(12),
        'xendit_refund_id' => null,
        'refunded_at' => null,
    ], $overrides));
}

test('admin can mark manual refund as completed', function () {
    $reservation = makeCancelledReservation();

    $response = $this->postJson(
        "/api/events/{$this->event->id}/reservations/{$reservation->ulid}/manual-refund",
        ['note' => 'Transferred via BCA mobile banking', 'bank_reference' => 'TRX-2026-0001']
    );

    $response->assertSuccessful();

    $reservation->refresh();
    expect($reservation->status)->toBe(ReservationStatus::Refunded)
        ->and($reservation->refunded_at)->not->toBeNull()
        ->and($reservation->xendit_refund_id)->toBeNull();
});

test('manual refund fails when reservation is not cancelled', function () {
    $reservation = Reservation::factory()->paid()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'refund_amount' => 1000000,
    ]);

    $this->postJson(
        "/api/events/{$this->event->id}/reservations/{$reservation->ulid}/manual-refund",
        ['note' => 'Should not work']
    )->assertStatus(422);
});

test('manual refund fails when already refunded', function () {
    $reservation = makeCancelledReservation([
        'status' => ReservationStatus::Refunded,
        'refunded_at' => now(),
    ]);

    $this->postJson(
        "/api/events/{$this->event->id}/reservations/{$reservation->ulid}/manual-refund",
        ['note' => 'Already done']
    )->assertStatus(422);
});

test('manual refund fails when xendit_refund_id already present', function () {
    $reservation = makeCancelledReservation([
        'xendit_refund_id' => 'rfnd_already_processing',
    ]);

    $this->postJson(
        "/api/events/{$this->event->id}/reservations/{$reservation->ulid}/manual-refund",
        ['note' => 'Should not work']
    )->assertStatus(422);
});

test('manual refund requires a note', function () {
    $reservation = makeCancelledReservation();

    $this->postJson(
        "/api/events/{$this->event->id}/reservations/{$reservation->ulid}/manual-refund",
        []
    )->assertStatus(422);
});

test('manual refund requires reservations.refund permission', function () {
    $other = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($other);

    $reservation = makeCancelledReservation();

    $this->postJson(
        "/api/events/{$this->event->id}/reservations/{$reservation->ulid}/manual-refund",
        ['note' => 'No permission']
    )->assertForbidden();
});

test('reservation resource exposes channel_supports_refund flag', function () {
    $vaReservation = Reservation::factory()->paid()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'payment_channel' => 'BCA',
    ]);

    $response = $this->getJson("/api/events/{$this->event->id}/reservations/{$vaReservation->ulid}");
    expect($response->json('data.payment.channel_supports_refund'))->toBeFalse();

    $cardReservation = Reservation::factory()->paid()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'payment_channel' => 'CREDIT_CARD',
    ]);

    $response = $this->getJson("/api/events/{$this->event->id}/reservations/{$cardReservation->ulid}");
    expect($response->json('data.payment.channel_supports_refund'))->toBeTrue();
});

test('reservation resource exposes manual_refund_pending flag', function () {
    $pending = makeCancelledReservation();
    $response = $this->getJson("/api/events/{$this->event->id}/reservations/{$pending->ulid}");
    expect($response->json('data.refund.manual_refund_pending'))->toBeTrue();

    $alreadyRefunded = makeCancelledReservation([
        'status' => ReservationStatus::Refunded,
        'refunded_at' => now(),
    ]);
    $response = $this->getJson("/api/events/{$this->event->id}/reservations/{$alreadyRefunded->ulid}");
    expect($response->json('data.refund.manual_refund_pending'))->toBeFalse();
});
