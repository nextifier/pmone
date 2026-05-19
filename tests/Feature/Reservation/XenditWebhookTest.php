<?php

use App\Enums\ReservationStatus;
use App\Models\Hotel;
use App\Models\ProjectPaymentGateway;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
});

/**
 * Attach an active Xendit gateway with a fixed webhook_token to the project
 * that owns this reservation, and return the per-project webhook URL.
 */
function setupWebhookForReservation(Reservation $reservation, string $token = 'test-callback-token'): string
{
    $project = $reservation->event->project;
    ProjectPaymentGateway::factory()->create([
        'project_id' => $project->id,
        'provider' => 'xendit',
        'mode' => 'test',
        'is_active' => true,
        'webhook_token' => $token,
    ]);

    return "/api/webhooks/xendit/{$project->username}";
}

test('webhook rejects request without valid signature', function () {
    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->create([
        'hotel_id' => $hotel->id,
        'status' => ReservationStatus::PendingPayment,
        'reservation_number' => 'HTL-20260101-XXXX',
    ]);
    $url = setupWebhookForReservation($reservation);

    $response = $this->postJson($url, [
        'external_id' => 'HTL-20260101-XXXX',
        'status' => 'PAID',
    ]);

    $response->assertStatus(401);
});

test('webhook marks reservation as paid on PAID event', function () {
    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->create([
        'hotel_id' => $hotel->id,
        'status' => ReservationStatus::PendingPayment,
        'reservation_number' => 'HTL-20260101-AAAA',
    ]);
    $url = setupWebhookForReservation($reservation);

    $response = $this->postJson($url, [
        'external_id' => 'HTL-20260101-AAAA',
        'id' => 'inv_xendit_999',
        'status' => 'PAID',
    ], ['x-callback-token' => 'test-callback-token']);

    $response->assertSuccessful();

    $reservation->refresh();
    expect($reservation->status)->toBe(ReservationStatus::Paid);
    expect($reservation->paid_at)->not->toBeNull();
    expect($reservation->xendit_invoice_id)->toBe('inv_xendit_999');
});

test('webhook expires reservation on EXPIRED event', function () {
    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->create([
        'hotel_id' => $hotel->id,
        'status' => ReservationStatus::PendingPayment,
        'reservation_number' => 'HTL-20260101-BBBB',
    ]);
    $url = setupWebhookForReservation($reservation);

    $response = $this->postJson($url, [
        'external_id' => 'HTL-20260101-BBBB',
        'id' => 'inv_xendit_000',
        'status' => 'EXPIRED',
    ], ['x-callback-token' => 'test-callback-token']);

    $response->assertSuccessful();

    $reservation->refresh();
    expect($reservation->status)->toBe(ReservationStatus::Expired);
});

test('webhook acknowledges unknown reservation with 200 to avoid retry storm', function () {
    // Need any reservation just to set up a project + gateway, then call webhook
    // with a different external_id that doesn't exist. Webhook handler now
    // returns 200 to prevent Xendit's exponential-backoff retry on a 4xx —
    // unknown external_id is logged and acknowledged.
    $reservation = Reservation::factory()->create([
        'reservation_number' => 'HTL-20260101-EXISTS',
    ]);
    $url = setupWebhookForReservation($reservation);

    $response = $this->postJson($url, [
        'external_id' => 'HTL-NON-EXISTENT',
        'status' => 'PAID',
    ], ['x-callback-token' => 'test-callback-token']);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Reservation not found (acknowledged)');
});

test('webhook is idempotent when PAID event fires twice', function () {
    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->create([
        'hotel_id' => $hotel->id,
        'status' => ReservationStatus::PendingPayment,
        'reservation_number' => 'HTL-20260101-CCCC',
    ]);
    $url = setupWebhookForReservation($reservation);

    $payload = [
        'external_id' => 'HTL-20260101-CCCC',
        'id' => 'inv_xendit_dup',
        'status' => 'PAID',
    ];
    $headers = ['x-callback-token' => 'test-callback-token'];

    $first = $this->postJson($url, $payload, $headers);
    $first->assertSuccessful();

    $reservation->refresh();
    $paidAt = $reservation->paid_at;

    $second = $this->postJson($url, $payload, $headers);
    $second->assertSuccessful()
        ->assertJsonPath('message', 'Reservation already paid');

    $reservation->refresh();
    expect($reservation->status)->toBe(ReservationStatus::Paid);
    expect($reservation->paid_at->toIso8601String())->toBe($paidAt->toIso8601String());
});

test('webhook rejects PAID event for cancelled reservation', function () {
    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->create([
        'hotel_id' => $hotel->id,
        'status' => ReservationStatus::Cancelled,
        'cancelled_at' => now()->subMinutes(10),
        'reservation_number' => 'HTL-20260101-EEEE',
    ]);
    $url = setupWebhookForReservation($reservation);

    $response = $this->postJson($url, [
        'external_id' => 'HTL-20260101-EEEE',
        'id' => 'inv_xendit_late_pay',
        'status' => 'PAID',
    ], ['x-callback-token' => 'test-callback-token']);

    $response->assertStatus(409)
        ->assertJsonPath('message', 'Reservation already in final state');

    $reservation->refresh();
    expect($reservation->status)->toBe(ReservationStatus::Cancelled);
});

test('webhook extracts payment_channel and destination from PAID payload', function () {
    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->create([
        'hotel_id' => $hotel->id,
        'status' => ReservationStatus::PendingPayment,
        'reservation_number' => 'HTL-20260101-FFFF',
    ]);
    $url = setupWebhookForReservation($reservation);

    $response = $this->postJson($url, [
        'external_id' => 'HTL-20260101-FFFF',
        'id' => 'inv_xendit_full_payload',
        'status' => 'PAID',
        'payment_method' => 'BANK_TRANSFER',
        'payment_channel' => 'BCA',
        'payment_destination' => '1234567890123456',
        'payment_id' => 'pay_xnd_abc999',
    ], ['x-callback-token' => 'test-callback-token']);

    $response->assertSuccessful();

    $reservation->refresh();
    expect($reservation->status)->toBe(ReservationStatus::Paid);
    expect($reservation->payment_channel)->toBe('BCA');
    expect($reservation->payment_destination)->toBe('1234567890123456');
    expect($reservation->xendit_payment_id)->toBe('pay_xnd_abc999');
});

test('webhook falls back to bank_code alias when payment_channel is missing', function () {
    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->create([
        'hotel_id' => $hotel->id,
        'status' => ReservationStatus::PendingPayment,
        'reservation_number' => 'HTL-20260101-GGGG',
    ]);
    $url = setupWebhookForReservation($reservation);

    $response = $this->postJson($url, [
        'external_id' => 'HTL-20260101-GGGG',
        'id' => 'inv_xendit_legacy_bank',
        'status' => 'PAID',
        'bank_code' => 'MANDIRI',
    ], ['x-callback-token' => 'test-callback-token']);

    $response->assertSuccessful();
    expect($reservation->fresh()->payment_channel)->toBe('MANDIRI');
});

test('webhook skips expire for already-paid reservation', function () {
    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->create([
        'hotel_id' => $hotel->id,
        'status' => ReservationStatus::Paid,
        'paid_at' => now()->subMinutes(5),
        'reservation_number' => 'HTL-20260101-DDDD',
    ]);
    $url = setupWebhookForReservation($reservation);

    $response = $this->postJson($url, [
        'external_id' => 'HTL-20260101-DDDD',
        'id' => 'inv_xendit_late',
        'status' => 'EXPIRED',
    ], ['x-callback-token' => 'test-callback-token']);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Reservation not eligible for expiry');

    $reservation->refresh();
    expect($reservation->status)->toBe(ReservationStatus::Paid);
});
