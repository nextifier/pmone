<?php

use App\Enums\ReservationStatus;
use App\Enums\Ticketing\TicketOrderStatus;
use App\Jobs\Reservation\SendBookingReceivedJob;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\Reservation;
use App\Models\TicketOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function midtransSign(array $p, string $serverKey): string
{
    return hash('sha512', ($p['order_id'] ?? '').($p['status_code'] ?? '').($p['gross_amount'] ?? '').$serverKey);
}

function pendingMidtransReservation($ctx, array $overrides = []): Reservation
{
    return Reservation::factory()->create(array_merge([
        'event_id' => $ctx->event->id,
        'hotel_id' => $ctx->hotel->id,
        'status' => ReservationStatus::PendingPayment,
        'payment_method' => 'midtrans',
        'payment_gateway_id' => $ctx->gateway->id,
        'xendit_invoice_id' => 'snap-token-keep',
        'total_amount' => 100000,
    ], $overrides));
}

/**
 * Build a signed notification payload for the given reservation number.
 *
 * @return array<string, mixed>
 */
function midtransNotification($ctx, string $orderId, array $extra = []): array
{
    $payload = array_merge([
        'order_id' => $orderId,
        'status_code' => '200',
        'gross_amount' => '100000.00',
        'transaction_status' => 'settlement',
        'transaction_id' => 'mid-txn-'.$orderId,
        'payment_type' => 'gopay',
    ], $extra);

    $payload['signature_key'] = midtransSign($payload, $ctx->serverKey);

    return $payload;
}

beforeEach(function () {
    Queue::fake();
    $this->serverKey = 'SB-Mid-server-WEBHOOKKEY123456789';
    $this->project = Project::factory()->create(['status' => 'active']);
    $this->event = Event::factory()->withoutPaymentGateway()->create(['project_id' => $this->project->id]);
    $this->hotel = Hotel::factory()->create();
    $this->gateway = ProjectPaymentGateway::factory()->for($this->project)->midtrans()->create([
        'is_active' => true,
        'secret_key' => $this->serverKey,
    ]);
});

it('marks the reservation paid on a valid settlement notification', function () {
    $reservation = pendingMidtransReservation($this, ['reservation_number' => 'HTL-MID-PAID-1']);

    $payload = midtransNotification($this, 'HTL-MID-PAID-1', [
        'transaction_id' => 'mid-txn-1',
        'payment_type' => 'bank_transfer',
        'va_numbers' => [['bank' => 'bca', 'va_number' => '12345678']],
    ]);

    $this->postJson('/api/webhooks/midtrans', $payload)
        ->assertSuccessful()
        ->assertJsonPath('message', 'Reservation marked as paid');

    $reservation->refresh();
    expect($reservation->status)->toBe(ReservationStatus::Paid)
        ->and($reservation->paid_at)->not->toBeNull()
        ->and($reservation->payment_channel)->toBe('BCA')
        ->and($reservation->payment_destination)->toBe('12345678')
        ->and($reservation->xendit_payment_id)->toBe('mid-txn-1')
        ->and($reservation->xendit_invoice_id)->toBe('snap-token-keep'); // Snap token preserved

    Queue::assertPushed(SendBookingReceivedJob::class, 1);
});

it('rejects a notification with a bad signature', function () {
    pendingMidtransReservation($this, ['reservation_number' => 'HTL-MID-BAD-1']);

    $this->postJson('/api/webhooks/midtrans', [
        'order_id' => 'HTL-MID-BAD-1',
        'status_code' => '200',
        'gross_amount' => '100000.00',
        'transaction_status' => 'settlement',
        'signature_key' => 'wrong-signature',
    ])->assertStatus(401);
});

it('marks paid on a credit-card capture with fraud_status accept', function () {
    $reservation = pendingMidtransReservation($this, ['reservation_number' => 'HTL-MID-CAP-1']);

    $this->postJson('/api/webhooks/midtrans', midtransNotification($this, 'HTL-MID-CAP-1', [
        'transaction_status' => 'capture',
        'fraud_status' => 'accept',
        'payment_type' => 'credit_card',
    ]))->assertSuccessful();

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Paid);
});

it('records the card network (VISA) as the payment channel for a card capture', function () {
    $reservation = pendingMidtransReservation($this, ['reservation_number' => 'HTL-MID-VISA-1']);

    $this->postJson('/api/webhooks/midtrans', midtransNotification($this, 'HTL-MID-VISA-1', [
        'transaction_status' => 'capture',
        'fraud_status' => 'accept',
        'payment_type' => 'credit_card',
        'masked_card' => '481111-1114',
    ]))->assertSuccessful();

    $fresh = $reservation->fresh();
    expect($fresh->status)->toBe(ReservationStatus::Paid)
        ->and($fresh->payment_channel)->toBe('VISA');
});

it('does not settle a capture flagged for fraud challenge', function () {
    $reservation = pendingMidtransReservation($this, ['reservation_number' => 'HTL-MID-CHAL-1']);

    $this->postJson('/api/webhooks/midtrans', midtransNotification($this, 'HTL-MID-CHAL-1', [
        'transaction_status' => 'capture',
        'fraud_status' => 'challenge',
        'payment_type' => 'credit_card',
    ]))->assertSuccessful()
        ->assertJsonPath('message', 'Payment under review (no action)');

    expect($reservation->fresh()->status)->toBe(ReservationStatus::PendingPayment);
    Queue::assertNotPushed(SendBookingReceivedJob::class);
});

it('expires the reservation on an expire notification', function () {
    $reservation = pendingMidtransReservation($this, ['reservation_number' => 'HTL-MID-EXP-1']);

    $this->postJson('/api/webhooks/midtrans', midtransNotification($this, 'HTL-MID-EXP-1', [
        'status_code' => '407',
        'transaction_status' => 'expire',
    ]))->assertSuccessful();

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Expired);
});

it('releases a pending reservation on deny', function () {
    $reservation = pendingMidtransReservation($this, ['reservation_number' => 'HTL-MID-DENY-1']);

    $this->postJson('/api/webhooks/midtrans', midtransNotification($this, 'HTL-MID-DENY-1', [
        'status_code' => '202',
        'transaction_status' => 'deny',
    ]))->assertSuccessful();

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Expired);
});

it('is idempotent: a duplicate settlement dispatches the email once', function () {
    $reservation = pendingMidtransReservation($this, ['reservation_number' => 'HTL-MID-DUP-1']);
    $payload = midtransNotification($this, 'HTL-MID-DUP-1');

    $this->postJson('/api/webhooks/midtrans', $payload)->assertSuccessful();
    $this->postJson('/api/webhooks/midtrans', $payload)
        ->assertSuccessful()
        ->assertJsonPath('message', 'Reservation already paid');

    Queue::assertPushed(SendBookingReceivedJob::class, 1);
    expect($reservation->fresh()->status)->toBe(ReservationStatus::Paid);
});

it('acknowledges with 200 when the order_id has no matching reservation', function () {
    $this->postJson('/api/webhooks/midtrans', [
        'order_id' => 'HTL-DOESNOTEXIST',
        'status_code' => '200',
        'gross_amount' => '1.00',
        'transaction_status' => 'settlement',
        'signature_key' => 'anything',
    ])->assertSuccessful()
        ->assertJsonPath('message', 'Reservation not found (acknowledged)');
});

it('returns 409 for a settlement on a final-state reservation', function () {
    pendingMidtransReservation($this, [
        'reservation_number' => 'HTL-MID-FINAL-1',
        'status' => ReservationStatus::Cancelled,
    ]);

    $this->postJson('/api/webhooks/midtrans', midtransNotification($this, 'HTL-MID-FINAL-1'))
        ->assertStatus(409);
});

it('resolves the reservation when order_id carries a retry suffix', function () {
    $reservation = pendingMidtransReservation($this, ['reservation_number' => 'HTL-MID-RETRY-1']);

    // Retry order_ids use a tilde suffix: HTL-MID-RETRY-1~2
    $this->postJson('/api/webhooks/midtrans', midtransNotification($this, 'HTL-MID-RETRY-1~2'))
        ->assertSuccessful();

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Paid);
});

it('logs the notification to payment_webhook_events attributed to the project', function () {
    pendingMidtransReservation($this, ['reservation_number' => 'HTL-MID-LOG-1']);

    $this->postJson('/api/webhooks/midtrans', midtransNotification($this, 'HTL-MID-LOG-1'))
        ->assertSuccessful();

    $this->assertDatabaseHas('payment_webhook_events', [
        'provider' => 'midtrans',
        'event_type' => 'midtrans.settlement',
        'external_id' => 'HTL-MID-LOG-1',
        'project_id' => $this->project->id,
    ]);
});

it('confirms a ticket order on a valid settlement notification', function () {
    $order = TicketOrder::factory()->create([
        'event_id' => $this->event->id,
        'order_number' => 'TIX-MID-PAID-1',
        'status' => TicketOrderStatus::PendingPayment,
        'payment_gateway_id' => $this->gateway->id,
        'xendit_invoice_id' => 'snap-token-keep',
        'total' => 100000,
    ]);

    $payload = midtransNotification($this, 'TIX-MID-PAID-1', [
        'transaction_id' => 'mid-tix-1',
        'payment_type' => 'gopay',
    ]);

    $this->postJson('/api/webhooks/midtrans', $payload)
        ->assertSuccessful()
        ->assertJsonPath('message', 'Ticket order confirmed');

    $order->refresh();
    expect($order->status)->toBe(TicketOrderStatus::Confirmed)
        ->and($order->paid_at)->not->toBeNull()
        ->and($order->payment_channel)->toBe('GOPAY');
});

it('expires a ticket order on a cancel notification', function () {
    $order = TicketOrder::factory()->create([
        'event_id' => $this->event->id,
        'order_number' => 'TIX-MID-EXP-1',
        'status' => TicketOrderStatus::PendingPayment,
        'payment_gateway_id' => $this->gateway->id,
        'total' => 100000,
    ]);

    $payload = midtransNotification($this, 'TIX-MID-EXP-1', ['transaction_status' => 'expire']);

    $this->postJson('/api/webhooks/midtrans', $payload)
        ->assertSuccessful()
        ->assertJsonPath('message', 'Ticket order expired');

    expect($order->refresh()->status)->toBe(TicketOrderStatus::Expired);
});

it('rejects a ticket notification with a bad signature', function () {
    TicketOrder::factory()->create([
        'event_id' => $this->event->id,
        'order_number' => 'TIX-MID-BAD-1',
        'status' => TicketOrderStatus::PendingPayment,
        'payment_gateway_id' => $this->gateway->id,
        'total' => 100000,
    ]);

    $payload = midtransNotification($this, 'TIX-MID-BAD-1');
    $payload['signature_key'] = 'tampered';

    $this->postJson('/api/webhooks/midtrans', $payload)->assertStatus(401);

    expect(TicketOrder::where('order_number', 'TIX-MID-BAD-1')->first()->status)
        ->toBe(TicketOrderStatus::PendingPayment);
});
