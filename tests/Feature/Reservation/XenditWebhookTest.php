<?php

use App\Enums\ReservationStatus;
use App\Models\Hotel;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('xendit.webhook_token', 'test-callback-token');
    Queue::fake();
});

test('webhook rejects request without valid signature', function () {
    $response = $this->postJson('/api/webhooks/xendit/invoice', [
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

    $response = $this->postJson('/api/webhooks/xendit/invoice', [
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

    $response = $this->postJson('/api/webhooks/xendit/invoice', [
        'external_id' => 'HTL-20260101-BBBB',
        'id' => 'inv_xendit_000',
        'status' => 'EXPIRED',
    ], ['x-callback-token' => 'test-callback-token']);

    $response->assertSuccessful();

    $reservation->refresh();
    expect($reservation->status)->toBe(ReservationStatus::Expired);
});

test('webhook returns 404 for unknown reservation', function () {
    $response = $this->postJson('/api/webhooks/xendit/invoice', [
        'external_id' => 'HTL-NON-EXISTENT',
        'status' => 'PAID',
    ], ['x-callback-token' => 'test-callback-token']);

    $response->assertStatus(404);
});

test('webhook is idempotent when PAID event fires twice', function () {
    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->create([
        'hotel_id' => $hotel->id,
        'status' => ReservationStatus::PendingPayment,
        'reservation_number' => 'HTL-20260101-CCCC',
    ]);

    $payload = [
        'external_id' => 'HTL-20260101-CCCC',
        'id' => 'inv_xendit_dup',
        'status' => 'PAID',
    ];
    $headers = ['x-callback-token' => 'test-callback-token'];

    $first = $this->postJson('/api/webhooks/xendit/invoice', $payload, $headers);
    $first->assertSuccessful();

    $reservation->refresh();
    $paidAt = $reservation->paid_at;

    $second = $this->postJson('/api/webhooks/xendit/invoice', $payload, $headers);
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

    $response = $this->postJson('/api/webhooks/xendit/invoice', [
        'external_id' => 'HTL-20260101-EEEE',
        'id' => 'inv_xendit_late_pay',
        'status' => 'PAID',
    ], ['x-callback-token' => 'test-callback-token']);

    $response->assertStatus(409)
        ->assertJsonPath('message', 'Reservation already in final state');

    $reservation->refresh();
    expect($reservation->status)->toBe(ReservationStatus::Cancelled);
});

test('webhook skips expire for already-paid reservation', function () {
    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->create([
        'hotel_id' => $hotel->id,
        'status' => ReservationStatus::Paid,
        'paid_at' => now()->subMinutes(5),
        'reservation_number' => 'HTL-20260101-DDDD',
    ]);

    $response = $this->postJson('/api/webhooks/xendit/invoice', [
        'external_id' => 'HTL-20260101-DDDD',
        'id' => 'inv_xendit_late',
        'status' => 'EXPIRED',
    ], ['x-callback-token' => 'test-callback-token']);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Reservation not eligible for expiry');

    $reservation->refresh();
    expect($reservation->status)->toBe(ReservationStatus::Paid);
});
