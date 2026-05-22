<?php

use App\Enums\ReservationStatus;
use App\Models\Hotel;
use App\Models\PaymentWebhookEvent;
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

test('qr.refund webhook finalises a cancelled reservation to refunded', function () {
    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->cancelled()->create([
        'hotel_id' => $hotel->id,
        'reservation_number' => 'HTL-20260101-QRRF',
        'xendit_payment_id' => 'qrpy_test_qr',
        'xendit_refund_id' => 'qrrf_test_123',
        'refund_amount' => 1160,
    ]);
    $url = setupWebhookForReservation($reservation);

    $response = $this->postJson($url, [
        'event' => 'qr.refund',
        'data' => [
            'id' => 'qrrf_test_123',
            'qrpy_id' => 'qrpy_test_qr',
            'status' => 'SUCCEEDED',
        ],
    ], ['x-callback-token' => 'test-callback-token']);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'QR refund settled');

    $reservation->refresh();
    expect($reservation->status)->toBe(ReservationStatus::Refunded)
        ->and($reservation->refunded_at)->not->toBeNull();
});

test('qr.refund webhook is idempotent when the reservation is already refunded', function () {
    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->paid()->create([
        'hotel_id' => $hotel->id,
        'status' => ReservationStatus::Refunded,
        'cancelled_at' => now()->subHour(),
        'refunded_at' => now()->subMinutes(30),
        'reservation_number' => 'HTL-20260101-QRR2',
        'xendit_payment_id' => 'qrpy_test_qr2',
        'xendit_refund_id' => 'qrrf_test_456',
        'refund_amount' => 1160,
    ]);
    $url = setupWebhookForReservation($reservation);

    $response = $this->postJson($url, [
        'event' => 'qr.refund',
        'data' => ['id' => 'qrrf_test_456', 'qrpy_id' => 'qrpy_test_qr2', 'status' => 'SUCCEEDED'],
    ], ['x-callback-token' => 'test-callback-token']);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'QR refund already synced');
});

test('qr.refund webhook ignores a reservation that is not in the refund flow', function () {
    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->paid()->create([
        'hotel_id' => $hotel->id,
        'reservation_number' => 'HTL-20260101-QRPD',
        'xendit_payment_id' => 'qrpy_still_paid',
    ]);
    $url = setupWebhookForReservation($reservation);

    $response = $this->postJson($url, [
        'event' => 'qr.refund',
        'data' => ['id' => 'qrrf_stray', 'qrpy_id' => 'qrpy_still_paid', 'status' => 'SUCCEEDED'],
    ], ['x-callback-token' => 'test-callback-token']);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Reservation not in refund flow (acknowledged)');

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Paid);
});

test('qr.payment webhook is acknowledged without action', function () {
    $reservation = Reservation::factory()->create([
        'reservation_number' => 'HTL-20260101-QRPY',
        'xendit_payment_id' => 'qrpy_pay_test',
    ]);
    $url = setupWebhookForReservation($reservation);

    $response = $this->postJson($url, [
        'event' => 'qr.payment',
        'data' => ['id' => 'qrpy_pay_test', 'status' => 'SUCCEEDED'],
    ], ['x-callback-token' => 'test-callback-token']);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'QR payment acknowledged (settled via invoice)');
});

test('generic webhook resolves the project from a qr.refund payload', function () {
    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->cancelled()->create([
        'hotel_id' => $hotel->id,
        'reservation_number' => 'HTL-20260101-QRGEN',
        'xendit_payment_id' => 'qrpy_generic_test',
        'xendit_refund_id' => 'qrrf_generic_789',
        'refund_amount' => 1160,
    ]);
    setupWebhookForReservation($reservation);

    $response = $this->postJson('/api/webhooks/xendit', [
        'event' => 'qr.refund',
        'data' => ['id' => 'qrrf_generic_789', 'qrpy_id' => 'qrpy_generic_test', 'status' => 'SUCCEEDED'],
    ], ['x-callback-token' => 'test-callback-token']);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'QR refund settled');

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Refunded);

    // The webhook audit log must attribute the qr.refund event to the project.
    $logged = PaymentWebhookEvent::query()->where('event_type', 'qr.refund')->first();
    expect($logged)->not->toBeNull()
        ->and($logged->project_id)->toBe($reservation->event->project_id);
});

test('webhook marks reservation as paid on payment_session.completed event', function () {
    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->create([
        'hotel_id' => $hotel->id,
        'status' => ReservationStatus::PendingPayment,
        'reservation_number' => 'HTL-20260101-SESS',
        'xendit_invoice_id' => 'ps-existing-session',
    ]);
    $url = setupWebhookForReservation($reservation);

    $response = $this->postJson($url, [
        'event' => 'payment_session.completed',
        'data' => [
            'id' => 'ps-existing-session',
            'reference_id' => 'HTL-20260101-SESS',
            'status' => 'COMPLETED',
            'amount' => 1500000,
            'payment_request_id' => 'pr-abc123',
        ],
    ], ['x-callback-token' => 'test-callback-token']);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Reservation marked as paid');

    $reservation->refresh();
    expect($reservation->status)->toBe(ReservationStatus::Paid);
    expect($reservation->paid_at)->not->toBeNull();
    expect($reservation->xendit_payment_id)->toBe('pr-abc123');
});

test('webhook expires reservation on payment_session.expired event', function () {
    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->create([
        'hotel_id' => $hotel->id,
        'status' => ReservationStatus::PendingPayment,
        'reservation_number' => 'HTL-20260101-SEXP',
        'xendit_invoice_id' => 'ps-expiring-session',
    ]);
    $url = setupWebhookForReservation($reservation);

    $response = $this->postJson($url, [
        'event' => 'payment_session.expired',
        'data' => [
            'id' => 'ps-expiring-session',
            'reference_id' => 'HTL-20260101-SEXP',
            'status' => 'EXPIRED',
        ],
    ], ['x-callback-token' => 'test-callback-token']);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Reservation expired');

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Expired);
});

test('payment_session webhook is idempotent for an already-paid reservation', function () {
    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->paid()->create([
        'hotel_id' => $hotel->id,
        'reservation_number' => 'HTL-20260101-SDUP',
        'xendit_invoice_id' => 'ps-paid-session',
    ]);
    $url = setupWebhookForReservation($reservation);

    $response = $this->postJson($url, [
        'event' => 'payment_session.completed',
        'data' => [
            'id' => 'ps-paid-session',
            'reference_id' => 'HTL-20260101-SDUP',
            'status' => 'COMPLETED',
        ],
    ], ['x-callback-token' => 'test-callback-token']);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Reservation already paid');
});

test('generic webhook resolves the project from a payment_session payload', function () {
    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->create([
        'hotel_id' => $hotel->id,
        'status' => ReservationStatus::PendingPayment,
        'reservation_number' => 'HTL-20260101-SGEN',
        'xendit_invoice_id' => 'ps-generic-session',
    ]);
    setupWebhookForReservation($reservation);

    $response = $this->postJson('/api/webhooks/xendit', [
        'event' => 'payment_session.completed',
        'data' => [
            'id' => 'ps-generic-session',
            'reference_id' => 'HTL-20260101-SGEN',
            'status' => 'COMPLETED',
        ],
    ], ['x-callback-token' => 'test-callback-token']);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Reservation marked as paid');

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Paid);
});
