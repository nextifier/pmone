<?php

use App\Enums\ReservationStatus;
use App\Jobs\Reservation\SendBookingReceivedJob;
use App\Models\Hotel;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->project = Project::factory()->create(['status' => 'active']);
    Queue::fake();
});

it('rejects webhook when callback token does not match any project gateway', function () {
    ProjectPaymentGateway::factory()->for($this->project)->create([
        'webhook_token' => 'correct-token',
        'is_active' => true,
    ]);

    $response = $this->postJson(
        "/api/webhooks/xendit/{$this->project->username}",
        ['external_id' => 'HTL-X', 'status' => 'paid'],
        ['x-callback-token' => 'wrong-token']
    );

    $response->assertStatus(401);
});

it('rejects webhook when project has no active gateway', function () {
    ProjectPaymentGateway::factory()->for($this->project)->inactive()->create([
        'webhook_token' => 'inactive-token',
    ]);

    $response = $this->postJson(
        "/api/webhooks/xendit/{$this->project->username}",
        ['external_id' => 'HTL-X', 'status' => 'paid'],
        ['x-callback-token' => 'inactive-token']
    );

    $response->assertStatus(401);
});

it('passes signature check with matching token and proceeds to payload handling', function () {
    ProjectPaymentGateway::factory()->for($this->project)->create([
        'webhook_token' => 'good-token',
        'is_active' => true,
    ]);

    // Reservation does not exist - signature verifies, payload handler returns 404.
    // This proves the 401 gate was passed.
    $response = $this->postJson(
        "/api/webhooks/xendit/{$this->project->username}",
        ['external_id' => 'HTL-DOESNOTEXIST', 'status' => 'paid'],
        ['x-callback-token' => 'good-token']
    );

    $response->assertStatus(404)
        ->assertJsonPath('message', 'Reservation not found');
});

it('rejects webhook with missing token header', function () {
    ProjectPaymentGateway::factory()->for($this->project)->create([
        'webhook_token' => 'good-token',
    ]);

    $response = $this->postJson(
        "/api/webhooks/xendit/{$this->project->username}",
        ['external_id' => 'HTL-X', 'status' => 'paid']
    );

    $response->assertStatus(401);
});

it('isolates project gateways - other project token does not pass for this project', function () {
    ProjectPaymentGateway::factory()->for($this->project)->create([
        'webhook_token' => 'project-a-token',
    ]);

    $otherProject = Project::factory()->create();
    ProjectPaymentGateway::factory()->for($otherProject)->create([
        'webhook_token' => 'project-b-token',
    ]);

    $response = $this->postJson(
        "/api/webhooks/xendit/{$this->project->username}",
        ['external_id' => 'HTL-X', 'status' => 'paid'],
        ['x-callback-token' => 'project-b-token']
    );

    $response->assertStatus(401);
});

it('refund.succeeded event syncs refunded_at on matching reservation', function () {
    ProjectPaymentGateway::factory()->for($this->project)->create([
        'webhook_token' => 'good-token',
    ]);

    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->create([
        'hotel_id' => $hotel->id,
        'status' => ReservationStatus::Paid,
        'paid_at' => now()->subDays(2),
        'xendit_invoice_id' => 'inv_to_refund_123',
        'refunded_at' => null,
    ]);

    $response = $this->postJson(
        "/api/webhooks/xendit/{$this->project->username}",
        [
            'event' => 'refund.succeeded',
            'id' => 'rfd_xnd_999',
            'invoice_id' => 'inv_to_refund_123',
            'amount' => 1000000,
        ],
        ['x-callback-token' => 'good-token']
    );

    $response->assertSuccessful();

    $reservation->refresh();
    expect($reservation->status)->toBe(ReservationStatus::Refunded);
    expect($reservation->refunded_at)->not->toBeNull();
    expect($reservation->xendit_refund_id)->toBe('rfd_xnd_999');
    expect((float) $reservation->refund_amount)->toBe(1000000.0);
});

it('refund.succeeded is idempotent when refunded_at already set', function () {
    ProjectPaymentGateway::factory()->for($this->project)->create([
        'webhook_token' => 'good-token',
    ]);

    $hotel = Hotel::factory()->create();
    $alreadyRefundedAt = now()->subDays(1);
    $reservation = Reservation::factory()->create([
        'hotel_id' => $hotel->id,
        'status' => ReservationStatus::Refunded,
        'xendit_invoice_id' => 'inv_already_refunded',
        'refunded_at' => $alreadyRefundedAt,
        'xendit_refund_id' => 'rfd_existing',
    ]);

    $this->postJson(
        "/api/webhooks/xendit/{$this->project->username}",
        [
            'event' => 'refund.succeeded',
            'id' => 'rfd_new_id',
            'invoice_id' => 'inv_already_refunded',
            'amount' => 500000,
        ],
        ['x-callback-token' => 'good-token']
    )->assertSuccessful();

    $reservation->refresh();
    expect($reservation->refunded_at->toIso8601String())->toBe($alreadyRefundedAt->toIso8601String());
    expect($reservation->xendit_refund_id)->toBe('rfd_existing');
});

it('refund.failed event logs and acknowledges without state change', function () {
    ProjectPaymentGateway::factory()->for($this->project)->create([
        'webhook_token' => 'good-token',
    ]);

    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->create([
        'hotel_id' => $hotel->id,
        'status' => ReservationStatus::Paid,
        'xendit_invoice_id' => 'inv_failed_refund',
    ]);

    $this->postJson(
        "/api/webhooks/xendit/{$this->project->username}",
        [
            'event' => 'refund.failed',
            'id' => 'rfd_failed',
            'invoice_id' => 'inv_failed_refund',
            'failure_reason' => 'INSUFFICIENT_BALANCE',
        ],
        ['x-callback-token' => 'good-token']
    )->assertSuccessful()
        ->assertJsonPath('message', 'Refund failure logged');

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Paid);
});

it('PAID webhook fired twice dispatches SendBookingReceivedJob exactly once (T3)', function () {
    ProjectPaymentGateway::factory()->for($this->project)->create([
        'webhook_token' => 'good-token',
    ]);

    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->create([
        'hotel_id' => $hotel->id,
        'status' => ReservationStatus::PendingPayment,
        'reservation_number' => 'HTL-T3-DUP-001',
        'xendit_invoice_id' => null,
    ]);

    $payload = [
        'external_id' => 'HTL-T3-DUP-001',
        'id' => 'inv_dup_xx',
        'status' => 'PAID',
        'payment_method' => 'BANK_TRANSFER',
        'payment_channel' => 'BCA',
    ];
    $headers = ['x-callback-token' => 'good-token'];

    $this->postJson("/api/webhooks/xendit/{$this->project->username}", $payload, $headers)
        ->assertSuccessful();

    // Second webhook (Xendit retry) — must be no-op for dispatch
    $this->postJson("/api/webhooks/xendit/{$this->project->username}", $payload, $headers)
        ->assertSuccessful()
        ->assertJsonPath('message', 'Reservation already paid');

    Queue::assertPushed(SendBookingReceivedJob::class, 1);

    $reservation->refresh();
    expect($reservation->status)->toBe(ReservationStatus::Paid);
    expect($reservation->payment_channel)->toBe('BCA');
});

it('refund.succeeded webhook fired twice keeps xendit_refund_id stable (T2)', function () {
    ProjectPaymentGateway::factory()->for($this->project)->create([
        'webhook_token' => 'good-token',
    ]);

    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->create([
        'hotel_id' => $hotel->id,
        'status' => ReservationStatus::Paid,
        'paid_at' => now()->subDays(2),
        'xendit_invoice_id' => 'inv_t2_refund',
    ]);

    $payload = [
        'event' => 'refund.succeeded',
        'id' => 'rfd_t2_first',
        'invoice_id' => 'inv_t2_refund',
        'amount' => 750000,
    ];
    $headers = ['x-callback-token' => 'good-token'];

    $this->postJson("/api/webhooks/xendit/{$this->project->username}", $payload, $headers)
        ->assertSuccessful();

    $firstSyncedAt = $reservation->fresh()->refunded_at;

    // Same refund event arrives again — must not change anything
    $this->postJson("/api/webhooks/xendit/{$this->project->username}", $payload, $headers)
        ->assertSuccessful()
        ->assertJsonPath('message', 'Refund already synced');

    $reservation->refresh();
    expect($reservation->xendit_refund_id)->toBe('rfd_t2_first');
    expect($reservation->refunded_at->toIso8601String())->toBe($firstSyncedAt->toIso8601String());
    expect((float) $reservation->refund_amount)->toBe(750000.0);
});

it('payment_method.* events return 200 (Phase 4 placeholder)', function () {
    ProjectPaymentGateway::factory()->for($this->project)->create([
        'webhook_token' => 'good-token',
    ]);

    $this->postJson(
        "/api/webhooks/xendit/{$this->project->username}",
        ['event' => 'payment_method.activated', 'id' => 'pm_xnd_abc'],
        ['x-callback-token' => 'good-token']
    )->assertSuccessful()
        ->assertJsonPath('message', 'Acknowledged (no action)');
});

it('unknown event with no recognizable shape returns 200 to prevent retry', function () {
    ProjectPaymentGateway::factory()->for($this->project)->create([
        'webhook_token' => 'good-token',
    ]);

    $this->postJson(
        "/api/webhooks/xendit/{$this->project->username}",
        ['event' => 'something.weird', 'foo' => 'bar'],
        ['x-callback-token' => 'good-token']
    )->assertSuccessful()
        ->assertJsonPath('message', 'Webhook received but no action taken');
});
