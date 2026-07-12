<?php

use App\Enums\Ticketing\TicketOrderStatus;
use App\Jobs\Ticket\SendAttendeeETicketJob;
use App\Jobs\Ticket\SendTicketOrderConfirmationJob;
use App\Models\Event;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\User;
use App\Services\Payment\PaymentProviderFactory;
use App\Services\Payment\TicketReconciliationService;
use App\Services\Ticket\TicketPurchaseService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->project = Project::factory()->create(['status' => 'active']);
    $this->event = Event::factory()->withoutPaymentGateway()->create([
        'project_id' => $this->project->id,
        'tickets_enabled' => true,
    ]);
    $this->service = app(TicketPurchaseService::class);
});

// ─── Hazard B (Step 1): unify the availability clock ─────────────────────
//
// Plan 016 made `sold_count` the AUTHORITATIVE counter for availableStock()
// (an atomic reserve()/release() pair, not a live SUM over order rows), so
// these two cases now set `sold_count` explicitly to the reservation a real
// createOrder() call would have made for this order, rather than relying on
// a SUM query to infer it from the order row alone.

it('still counts a time-expired but not-yet-flipped pending order as holding its seat (no oversell)', function () {
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'stock' => 1]);

    $order = TicketOrder::factory()->create([
        'event_id' => $this->event->id,
        'status' => TicketOrderStatus::PendingPayment,
        // The hard-expiry job runs every 15 min - this order's window lapsed
        // a minute ago but the job has not caught up yet.
        'payment_expires_at' => now()->subMinute(),
    ]);
    $order->items()->create(['ticket_id' => $ticket->id, 'quantity' => 1, 'unit_price' => 0, 'subtotal' => 0]);
    $ticket->increment('sold_count', 1);

    // The counter has no notion of the order's clock at all - it only
    // changes on an explicit reserve/release call - so a still-PendingPayment
    // order holds its seat regardless of how stale `payment_expires_at` is,
    // exactly as it did under the old soft-clock-immune SUM read.
    expect($this->service->availableStock($ticket->fresh()))->toBe(0);
});

it('still frees the seat once the order actually flips to Expired', function () {
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'stock' => 1]);

    $order = TicketOrder::factory()->create([
        'event_id' => $this->event->id,
        'status' => TicketOrderStatus::PendingPayment,
        'payment_expires_at' => now()->subMinute(),
    ]);
    $order->items()->create(['ticket_id' => $ticket->id, 'quantity' => 1, 'unit_price' => 0, 'subtotal' => 0]);
    $ticket->increment('sold_count', 1);

    $this->service->expireOrder($order);

    expect($this->service->availableStock($ticket->fresh()))->toBe(1);
});

// ─── Hazard D (Step 2): markAsConfirmed reports whether it actually flipped ─

it('markAsConfirmed returns true when it performs the flip', function () {
    Bus::fake();
    $order = TicketOrder::factory()->create([
        'event_id' => $this->event->id,
        'status' => TicketOrderStatus::PendingPayment,
    ]);

    $result = $this->service->markAsConfirmed($order, ['id' => 'inv_flip']);

    expect($result)->toBeTrue()
        ->and($order->fresh()->status)->toBe(TicketOrderStatus::Confirmed);
});

it('markAsConfirmed returns false when the order is already confirmed', function () {
    $order = TicketOrder::factory()->confirmed()->create(['event_id' => $this->event->id]);

    $result = $this->service->markAsConfirmed($order, ['id' => 'inv_noop']);

    expect($result)->toBeFalse();
});

it('manual mark-paid after a webhook already confirmed does not stamp manual fields', function () {
    Bus::fake();

    $this->seed(RoleAndPermissionSeeder::class);
    $staff = User::factory()->create(['email_verified_at' => now()]);
    $staff->assignRole('master');
    $this->actingAs($staff);

    $order = TicketOrder::factory()->create([
        'event_id' => $this->event->id,
        'status' => TicketOrderStatus::PendingPayment,
    ]);

    // A webhook wins the race between the controller's stale abort_if read
    // and its call into markAsConfirmed: simulate that by making the service
    // report "no flip happened" regardless of the order's apparent status.
    $this->mock(TicketPurchaseService::class, function ($mock) {
        $mock->shouldReceive('markAsConfirmed')->once()->andReturn(false);
    });

    $response = $this->postJson("/api/events/{$this->event->id}/ticket-orders/{$order->ulid}/mark-paid", [
        'payment_channel' => 'BCA',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Ticket order was already confirmed by a payment webhook; no manual action taken.');

    $order->refresh();
    expect($order->marked_paid_manually_at)->toBeNull()
        ->and($order->marked_paid_by)->toBeNull();

    expect(
        Activity::query()
            ->where('event', 'ticket_order_marked_paid_manual')
            ->where('properties->ticket_order_id', $order->id)
            ->exists()
    )->toBeFalse();
});

// ─── Hazard C (Step 3): stop over-expiring a ticket order on a Midtrans decline ─

function midtransTicketSignature(array $p, string $serverKey): string
{
    return hash('sha512', ($p['order_id'] ?? '').($p['status_code'] ?? '').($p['gross_amount'] ?? '').$serverKey);
}

it('leaves a ticket order pending payment and payable on a Midtrans deny/cancel/failure', function (string $status) {
    $serverKey = 'SB-Mid-server-WEBHOOKKEY123456789';
    $gateway = ProjectPaymentGateway::factory()->for($this->project)->midtrans()->create([
        'is_active' => true,
        'secret_key' => $serverKey,
    ]);

    $order = TicketOrder::factory()->create([
        'event_id' => $this->event->id,
        'order_number' => 'TIX-MID-DECLINE-'.strtoupper($status),
        'status' => TicketOrderStatus::PendingPayment,
        'payment_gateway_id' => $gateway->id,
        'total' => 100000,
    ]);

    $payload = [
        'order_id' => $order->order_number,
        'status_code' => '202',
        'gross_amount' => '100000.00',
        'transaction_status' => $status,
        'transaction_id' => 'mid-decline-'.$status,
        'payment_type' => 'credit_card',
    ];
    $payload['signature_key'] = midtransTicketSignature($payload, $serverKey);

    $this->postJson('/api/webhooks/midtrans', $payload)
        ->assertSuccessful()
        ->assertJsonPath('message', 'Webhook received but no action taken');

    expect($order->fresh()->status)->toBe(TicketOrderStatus::PendingPayment);
})->with([
    'deny' => 'deny',
    'cancel' => 'cancel',
    'failure' => 'failure',
]);

it('still expires a ticket order on a genuine Midtrans expire', function () {
    $serverKey = 'SB-Mid-server-WEBHOOKKEY123456789';
    $gateway = ProjectPaymentGateway::factory()->for($this->project)->midtrans()->create([
        'is_active' => true,
        'secret_key' => $serverKey,
    ]);

    $order = TicketOrder::factory()->create([
        'event_id' => $this->event->id,
        'order_number' => 'TIX-MID-REALEXPIRE-1',
        'status' => TicketOrderStatus::PendingPayment,
        'payment_gateway_id' => $gateway->id,
        'total' => 100000,
    ]);

    $payload = [
        'order_id' => $order->order_number,
        'status_code' => '407',
        'gross_amount' => '100000.00',
        'transaction_status' => 'expire',
        'transaction_id' => 'mid-real-expire',
        'payment_type' => 'bank_transfer',
    ];
    $payload['signature_key'] = midtransTicketSignature($payload, $serverKey);

    $this->postJson('/api/webhooks/midtrans', $payload)
        ->assertSuccessful()
        ->assertJsonPath('message', 'Ticket order expired');

    expect($order->fresh()->status)->toBe(TicketOrderStatus::Expired);
});

// ─── Hazard A (Step 4): honor a late payment instead of a dead-end 409 ────

it('reconfirmAfterExpiry resurrects an expired order when stock is still available', function () {
    Bus::fake();
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'stock' => 5]);
    $ticket->forceFill(['sold_count' => 0])->save();

    $order = TicketOrder::factory()->create([
        'event_id' => $this->event->id,
        'status' => TicketOrderStatus::Expired,
        'buyer_email' => 'late-payer@example.com',
    ]);
    $order->items()->create(['ticket_id' => $ticket->id, 'quantity' => 2, 'unit_price' => 0, 'subtotal' => 0]);

    $outcome = $this->service->reconfirmAfterExpiry($order, ['id' => 'inv_late_ok', 'amount' => 0]);

    expect($outcome)->toBe('reconfirmed')
        ->and($order->fresh()->status)->toBe(TicketOrderStatus::Confirmed)
        ->and($order->fresh()->paid_after_expiry_at)->toBeNull()
        ->and($ticket->fresh()->sold_count)->toBe(2);

    Bus::assertDispatched(SendTicketOrderConfirmationJob::class, fn ($j) => $j->ticketOrderId === $order->id);
});

it('reconfirmAfterExpiry records for reconciliation instead of overselling when stock is gone', function () {
    Bus::fake();
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'stock' => 2]);

    // Both seats already sold to a DIFFERENT (later) confirmed order that
    // grabbed the seats this order released when it expired. availableStock()
    // derives from live order rows (not the cached sold_count column), so the
    // competing order must actually exist for the no-oversell check to bite.
    $competitor = TicketOrder::factory()->confirmed()->create(['event_id' => $this->event->id]);
    $competitor->items()->create(['ticket_id' => $ticket->id, 'quantity' => 2, 'unit_price' => 0, 'subtotal' => 0]);
    $ticket->forceFill(['sold_count' => 2])->save();

    $order = TicketOrder::factory()->create([
        'event_id' => $this->event->id,
        'status' => TicketOrderStatus::Expired,
        'buyer_email' => 'too-late@example.com',
    ]);
    $order->items()->create(['ticket_id' => $ticket->id, 'quantity' => 1, 'unit_price' => 0, 'subtotal' => 0]);

    $outcome = $this->service->reconfirmAfterExpiry($order, ['id' => 'inv_late_sold_out', 'amount' => 0]);

    expect($outcome)->toBe('needs_reconciliation')
        ->and($order->fresh()->status)->toBe(TicketOrderStatus::Expired)
        ->and($order->fresh()->paid_after_expiry_at)->not->toBeNull()
        ->and($ticket->fresh()->sold_count)->toBe(2);

    Bus::assertNotDispatched(SendTicketOrderConfirmationJob::class);
    Bus::assertNotDispatched(SendAttendeeETicketJob::class);

    expect(
        Activity::query()
            ->where('event', 'payment_needs_reconciliation')
            ->where('properties->ticket_order_id', $order->id)
            ->exists()
    )->toBeTrue();
});

it('cannot double-count sold_count when the same paid-after-expiry event is redelivered', function () {
    Bus::fake();
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'stock' => 5]);
    $ticket->forceFill(['sold_count' => 0])->save();

    $order = TicketOrder::factory()->create([
        'event_id' => $this->event->id,
        'status' => TicketOrderStatus::Expired,
    ]);
    $order->items()->create(['ticket_id' => $ticket->id, 'quantity' => 2, 'unit_price' => 0, 'subtotal' => 0]);
    // Pre-load relations once, mirroring a caller that holds on to the same
    // $order instance across a redelivered webhook - its in-memory `status`
    // attribute stays "Expired" even after the first call flips the DB row.
    $order->loadMissing('items.ticket', 'items.ticketSession');

    $first = $this->service->reconfirmAfterExpiry($order, ['id' => 'inv_dup_redelivery']);
    $second = $this->service->reconfirmAfterExpiry($order, ['id' => 'inv_dup_redelivery']);

    expect($first)->toBe('reconfirmed')
        ->and($second)->toBe('already_final')
        ->and($ticket->fresh()->sold_count)->toBe(2);
});

it('Xendit paid webhook re-confirms an expired ticket order when stock still fits', function () {
    Bus::fake();
    $gateway = ProjectPaymentGateway::factory()->for($this->project)->create(['webhook_token' => 'good-token']);
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'stock' => 5]);
    $ticket->forceFill(['sold_count' => 0])->save();

    $order = TicketOrder::factory()->create([
        'event_id' => $this->event->id,
        'order_number' => 'TIX-XND-LATE-OK-1',
        'status' => TicketOrderStatus::Expired,
        'payment_gateway_id' => $gateway->id,
        'total' => 200000,
        'buyer_email' => 'late-xendit@example.com',
    ]);
    $order->items()->create(['ticket_id' => $ticket->id, 'quantity' => 2, 'unit_price' => 100000, 'subtotal' => 200000]);

    $response = $this->postJson(
        "/api/webhooks/xendit/{$this->project->username}",
        ['external_id' => 'TIX-XND-LATE-OK-1', 'id' => 'inv_xnd_late_ok', 'status' => 'PAID', 'amount' => 200000],
        ['x-callback-token' => 'good-token']
    );

    $response->assertSuccessful()->assertJsonPath('message', 'Ticket order re-confirmed after expiry');

    expect($order->fresh()->status)->toBe(TicketOrderStatus::Confirmed)
        ->and($ticket->fresh()->sold_count)->toBe(2);

    expect(
        Activity::query()
            ->where('event', 'payment_paid_after_expiry')
            ->where('properties->ticket_order_id', $order->id)
            ->exists()
    )->toBeTrue();
});

it('Xendit paid webhook records for reconciliation (no oversell) when stock ran out after expiry', function () {
    Bus::fake();
    $gateway = ProjectPaymentGateway::factory()->for($this->project)->create(['webhook_token' => 'good-token']);
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'stock' => 1]);

    // The freed seat was resold to a different confirmed order.
    // availableStock() derives from live order rows, so the competing order
    // must actually exist for the no-oversell check to bite.
    $competitor = TicketOrder::factory()->confirmed()->create(['event_id' => $this->event->id]);
    $competitor->items()->create(['ticket_id' => $ticket->id, 'quantity' => 1, 'unit_price' => 0, 'subtotal' => 0]);
    $ticket->forceFill(['sold_count' => 1])->save();

    $order = TicketOrder::factory()->create([
        'event_id' => $this->event->id,
        'order_number' => 'TIX-XND-LATE-SOLDOUT-1',
        'status' => TicketOrderStatus::Expired,
        'payment_gateway_id' => $gateway->id,
        'total' => 100000,
    ]);
    $order->items()->create(['ticket_id' => $ticket->id, 'quantity' => 1, 'unit_price' => 100000, 'subtotal' => 100000]);

    $response = $this->postJson(
        "/api/webhooks/xendit/{$this->project->username}",
        ['external_id' => 'TIX-XND-LATE-SOLDOUT-1', 'id' => 'inv_xnd_late_full', 'status' => 'PAID', 'amount' => 100000],
        ['x-callback-token' => 'good-token']
    );

    $response->assertSuccessful();

    expect($order->fresh()->status)->toBe(TicketOrderStatus::Expired)
        ->and($order->fresh()->paid_after_expiry_at)->not->toBeNull()
        ->and($ticket->fresh()->sold_count)->toBe(1);
});

// ─── Hazard A safety net (Step 5): ticket-order reconciliation ────────────

it('reconciliation flags a paid-but-pending ticket order', function () {
    $gateway = ProjectPaymentGateway::factory()->for($this->project)->create();

    TicketOrder::factory()->create([
        'event_id' => $this->event->id,
        'order_number' => 'TIX-RECON-STUCK-1',
        'status' => TicketOrderStatus::Expired,
        'total' => 150000,
    ]);

    Http::fake([
        'https://api.xendit.co/transactions*' => Http::response([
            'has_more' => false,
            'data' => [
                ['id' => 'txn_stuck', 'type' => 'PAYMENT', 'status' => 'SUCCESS', 'amount' => 150000, 'currency' => 'IDR', 'reference_id' => 'TIX-RECON-STUCK-1'],
            ],
        ], 200),
    ]);

    $provider = app(PaymentProviderFactory::class)->make($gateway);
    $report = app(TicketReconciliationService::class)->reconcile($provider, $this->project->id, '2026-07-01', '2026-07-31');

    expect($report->matchedCount)->toBe(0)
        ->and($report->discrepancies)->toHaveCount(1)
        ->and($report->discrepancies[0]->type)->toBe('status_mismatch')
        ->and($report->discrepancies[0]->orderNumber)->toBe('TIX-RECON-STUCK-1');
});

it('reconciliation reports a clean match when the gateway payment and a confirmed ticket order agree', function () {
    $gateway = ProjectPaymentGateway::factory()->for($this->project)->create();

    TicketOrder::factory()->confirmed()->create([
        'event_id' => $this->event->id,
        'order_number' => 'TIX-RECON-OK-1',
        'total' => 90000,
    ]);

    Http::fake([
        'https://api.xendit.co/transactions*' => Http::response([
            'has_more' => false,
            'data' => [
                ['id' => 'txn_ok', 'type' => 'PAYMENT', 'status' => 'SUCCESS', 'amount' => 90000, 'currency' => 'IDR', 'reference_id' => 'TIX-RECON-OK-1'],
            ],
        ], 200),
    ]);

    $provider = app(PaymentProviderFactory::class)->make($gateway);
    $report = app(TicketReconciliationService::class)->reconcile($provider, $this->project->id, '2026-07-01', '2026-07-31');

    expect($report->matchedCount)->toBe(1)
        ->and($report->discrepancies)->toHaveCount(0);
});
