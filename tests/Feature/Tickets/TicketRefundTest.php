<?php

use App\Contracts\Payment\CreatesCheckout;
use App\Enums\Ticketing\TicketOrderStatus;
use App\Models\Event;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketPricePhase;
use App\Models\User;
use App\Services\Ticket\AttendeeService;
use App\Services\Ticket\ScanService;
use App\Services\Ticket\TicketPurchaseService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);

    $this->project = Project::factory()->create();
    $this->event = Event::factory()->withoutPaymentGateway()->create([
        'project_id' => $this->project->id,
        'tickets_enabled' => true,
    ]);
    $this->purchases = app(TicketPurchaseService::class);
});

/**
 * Purchase a real N-attendee ticket order through the actual purchase flow
 * (not factory shortcuts), so item->ticket_price_phase_id and the
 * ticket/phase sold_count counters are wired exactly like a genuine sale -
 * refundAttendee's release logic needs the real counters to assert against.
 * A non-zero price confirms via a mocked gateway checkout + markAsConfirmed
 * (mirrors a real webhook), so the order carries a genuine nonzero total.
 */
function refundableOrder(Event $event, int $qty, float $price = 50000): TicketOrder
{
    // max_quantity defaults to null (uncapped) so a multi-attendee purchase
    // here isn't flaky against the factory's random optional() cap (mirrors
    // PurchaseInventoryIntegrityTest's priceableTicket()).
    $ticket = Ticket::factory()->create(['event_id' => $event->id, 'stock' => 100, 'max_quantity' => null]);
    TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id,
        'price' => $price,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    $service = app(TicketPurchaseService::class);
    $client = null;

    if ($price > 0) {
        ProjectPaymentGateway::factory()->create([
            'project_id' => $event->project_id,
            'mode' => 'test',
            'is_active' => true,
        ]);

        $client = mock(CreatesCheckout::class);
        $client->shouldReceive('createCheckout')->once()->andReturn([
            'reference' => 'ref_refund_'.Str::random(8),
            'payment_url' => 'https://pay.example/refund-test',
            'checkout_method' => 'payment_link_legacy',
        ]);
        $client->shouldReceive('gateway')->andReturnNull();
    }

    $order = $service->createOrder([
        'event_id' => $event->id,
        'buyer_name' => 'Budi',
        'buyer_email' => 'budi@example.com',
        'buyer_phone' => '0812',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => $qty]],
    ], $client);

    if ($price > 0) {
        $service->markAsConfirmed($order, []);
    }

    return $order->fresh(['items.attendees', 'event']);
}

it('refunds one of three attendees: voids the seat, keeps the order confirmed, restores stock, and recomputes the total', function () {
    $order = refundableOrder($this->event, 3, 50000);
    $ticket = $order->items->first()->ticket;
    $phase = TicketPricePhase::where('ticket_id', $ticket->id)->first();

    expect($order->status)->toBe(TicketOrderStatus::Confirmed)
        ->and((float) $order->total)->toBe(150000.0)
        ->and($ticket->fresh()->sold_count)->toBe(3)
        ->and($phase->fresh()->sold_count)->toBe(3)
        ->and($this->purchases->availableStock($ticket->fresh()))->toBe(97);

    $attendee = $order->attendees->first();

    $this->purchases->refundAttendee($attendee, 'Customer requested', $this->user->id);

    $attendeeFresh = $attendee->fresh();
    $orderFresh = $order->fresh();
    $itemFresh = $orderFresh->items->first();

    expect($attendeeFresh->cancelled_at)->not->toBeNull()
        ->and($attendeeFresh->cancelled_reason)->toBe('Customer requested')
        ->and($attendeeFresh->cancelled_by)->toBe($this->user->id)
        ->and($orderFresh->status)->toBe(TicketOrderStatus::Confirmed)
        ->and($itemFresh->quantity)->toBe(2)
        ->and((float) $itemFresh->subtotal)->toBe(100000.0)
        ->and((float) $orderFresh->total)->toBe(100000.0)
        ->and($ticket->fresh()->sold_count)->toBe(2)
        ->and($phase->fresh()->sold_count)->toBe(2)
        ->and($this->purchases->availableStock($ticket->fresh()))->toBe(98);
});

it('is idempotent when refunding an attendee that is already cancelled', function () {
    $order = refundableOrder($this->event, 2, 50000);
    $attendee = $order->attendees->first();

    $this->purchases->refundAttendee($attendee, 'first reason', $this->user->id);
    $tokenAfterFirst = $attendee->fresh()->qr_token;

    $this->purchases->refundAttendee($attendee->fresh(), 'second reason', $this->user->id);

    $fresh = $attendee->fresh();
    expect($fresh->qr_token)->toBe($tokenAfterFirst)
        ->and($fresh->cancelled_reason)->toBe('first reason')
        ->and($order->fresh()->items->first()->quantity)->toBe(1);
});

it('blocks a cancelled attendee at check-in with ticket_cancelled, at both the service and endpoint level', function () {
    $order = refundableOrder($this->event, 1, 0);
    $attendee = $order->attendees->first();

    $this->purchases->refundAttendee($attendee, null, $this->user->id);
    $attendee->refresh();

    $serviceResult = app(ScanService::class)->checkIn($attendee->qr_token, $this->event, $this->user->id, (string) Str::uuid());
    expect($serviceResult['result'])->toBe('invalid')
        ->and($serviceResult['reason'])->toBe('ticket_cancelled');

    $scanner = User::factory()->create(['email_verified_at' => now()]);
    $scanner->assignRole('scanner');

    $this->actingAs($scanner)
        ->postJson("/api/events/{$this->event->id}/scan/check-in", [
            'qr_token' => $attendee->qr_token,
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.result', 'invalid')
        ->assertJsonPath('data.reason', 'ticket_cancelled');
});

it('rotates the cancelled attendee qr_token so the old one no longer resolves', function () {
    $order = refundableOrder($this->event, 1, 0);
    $attendee = $order->attendees->first();
    $oldToken = $attendee->qr_token;

    $this->purchases->refundAttendee($attendee, null, $this->user->id);

    $fresh = $attendee->fresh();
    expect($fresh->qr_token)->not->toBe($oldToken);

    $result = app(ScanService::class)->checkIn($oldToken, $this->event, $this->user->id, (string) Str::uuid());
    expect($result['result'])->toBe('invalid')
        ->and($result['reason'])->toBe('ticket_not_found');
});

it('excludes a cancelled attendee from the offline manifest and search', function () {
    $order = refundableOrder($this->event, 2, 0);
    $attendees = $order->attendees()->get();
    $keep = $attendees[0];
    $cancel = $attendees[1];
    $cancelOldToken = $cancel->qr_token;

    $this->purchases->refundAttendee($cancel, null, $this->user->id);

    $scan = app(ScanService::class);

    $manifestTokens = collect($scan->manifest($this->event))->pluck('qr_token');
    expect($manifestTokens->contains($keep->qr_token))->toBeTrue()
        ->and($manifestTokens->contains($cancelOldToken))->toBeFalse()
        ->and($manifestTokens)->toHaveCount(1);

    $results = collect($scan->search($this->event, $order->order_number));
    expect($results->pluck('qr_token')->contains($keep->qr_token))->toBeTrue()
        ->and($results)->toHaveCount(1);
});

it('settles a Xendit refund webhook: flips the order to Refunded, voids every attendee, and is idempotent on redelivery', function () {
    $order = refundableOrder($this->event, 2, 60000);
    $order->update(['xendit_invoice_id' => 'inv_refund_test_1']);

    // Explicit label outside the factory's randomElement(['Production',
    // 'Sandbox', null]) pool - refundableOrder() already created a
    // 'xendit'/'test'-mode gateway on this project with a random label, and
    // the (project_id, provider, mode, label) unique constraint would
    // occasionally collide with a same-mode, same-random-label gateway here.
    ProjectPaymentGateway::factory()->for($this->project)->create([
        'label' => 'Refund Webhook Gateway',
        'webhook_token' => 'wt-refund-1',
        'is_active' => true,
    ]);

    $payload = [
        'event' => 'refund.succeeded',
        'data' => [
            'id' => 'rf_test_1',
            'invoice_id' => 'inv_refund_test_1',
            'amount' => 120000,
        ],
    ];

    $this->postJson(
        "/api/webhooks/xendit/{$this->project->username}",
        $payload,
        ['x-callback-token' => 'wt-refund-1']
    )
        ->assertSuccessful()
        ->assertJsonPath('message', 'Refund finalized synced');

    $orderFresh = $order->fresh();
    $ticket = $order->items->first()->ticket->fresh();

    expect($orderFresh->status)->toBe(TicketOrderStatus::Refunded)
        ->and($orderFresh->attendees()->whereNotNull('cancelled_at')->count())->toBe(2)
        ->and((float) $orderFresh->total)->toBe(0.0)
        ->and($ticket->sold_count)->toBe(0);

    // Redelivery of the same event must not double-release stock.
    $this->postJson(
        "/api/webhooks/xendit/{$this->project->username}",
        $payload,
        ['x-callback-token' => 'wt-refund-1']
    )
        ->assertSuccessful()
        ->assertJsonPath('message', 'Refund already synced');

    expect($ticket->fresh()->sold_count)->toBe(0);
});

it('flags a Midtrans partial_refund for manual review without voiding any attendee', function () {
    $order = refundableOrder($this->event, 2, 60000);

    $gateway = ProjectPaymentGateway::factory()->for($this->project)->midtrans()->create([
        'is_active' => true,
        'secret_key' => 'SB-Mid-server-REFUNDTESTKEY123',
    ]);
    $order->update(['payment_gateway_id' => $gateway->id]);

    $payload = [
        'order_id' => $order->order_number,
        'status_code' => '200',
        'gross_amount' => '60000.00',
        'transaction_status' => 'partial_refund',
        'transaction_id' => 'mid-txn-partial-1',
    ];
    $payload['signature_key'] = hash(
        'sha512',
        $payload['order_id'].$payload['status_code'].$payload['gross_amount'].$gateway->secret_key,
    );

    $this->postJson('/api/webhooks/midtrans', $payload)
        ->assertSuccessful()
        ->assertJsonPath('message', 'Partial refund received; flagged for manual review');

    $orderFresh = $order->fresh();
    expect($orderFresh->status)->toBe(TicketOrderStatus::Confirmed)
        ->and($orderFresh->attendees()->whereNotNull('cancelled_at')->count())->toBe(0);
});

it('settles a full Midtrans refund: flips the order to Refunded and voids every attendee', function () {
    $order = refundableOrder($this->event, 2, 60000);

    $gateway = ProjectPaymentGateway::factory()->for($this->project)->midtrans()->create([
        'is_active' => true,
        'secret_key' => 'SB-Mid-server-REFUNDTESTKEY456',
    ]);
    $order->update(['payment_gateway_id' => $gateway->id]);

    $payload = [
        'order_id' => $order->order_number,
        'status_code' => '200',
        'gross_amount' => '120000.00',
        'transaction_status' => 'refund',
        'transaction_id' => 'mid-txn-refund-1',
    ];
    $payload['signature_key'] = hash(
        'sha512',
        $payload['order_id'].$payload['status_code'].$payload['gross_amount'].$gateway->secret_key,
    );

    $this->postJson('/api/webhooks/midtrans', $payload)
        ->assertSuccessful()
        ->assertJsonPath('message', 'Refund finalized synced');

    $orderFresh = $order->fresh();
    expect($orderFresh->status)->toBe(TicketOrderStatus::Refunded)
        ->and($orderFresh->attendees()->whereNotNull('cancelled_at')->count())->toBe(2);
});

it('rotates the qr_token when staff transfers an attendee to a new email, invalidating the old token', function () {
    $order = refundableOrder($this->event, 1, 0);
    $attendee = $order->attendees->first();
    $oldToken = $attendee->qr_token;

    app(AttendeeService::class)->applyStaffEdit(
        $attendee,
        ['email' => 'new-holder@example.com'],
        $this->event,
        $this->user->id,
    );

    $fresh = $attendee->fresh();
    expect($fresh->email)->toBe('new-holder@example.com')
        ->and($fresh->qr_token)->not->toBe($oldToken);

    $oldResult = app(ScanService::class)->checkIn($oldToken, $this->event, $this->user->id, (string) Str::uuid());
    expect($oldResult['result'])->toBe('invalid')
        ->and($oldResult['reason'])->toBe('ticket_not_found');

    $newResult = app(ScanService::class)->checkIn($fresh->qr_token, $this->event, $this->user->id, (string) Str::uuid());
    expect($newResult['result'])->toBe('checked_in');
});

it('gates the refund endpoint behind attendees.refund and refunds successfully with it', function () {
    $order = refundableOrder($this->event, 2, 40000);
    $attendee = $order->attendees->first();

    $plain = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($plain)
        ->postJson("/api/events/{$this->event->id}/attendees/{$attendee->id}/refund", ['reason' => 'test'])
        ->assertForbidden();

    expect($attendee->fresh()->cancelled_at)->toBeNull();

    $this->actingAs($this->user)
        ->postJson("/api/events/{$this->event->id}/attendees/{$attendee->id}/refund", ['reason' => 'Duplicate purchase'])
        ->assertSuccessful()
        ->assertJsonPath('message', 'Attendee ticket refunded.');

    $fresh = $attendee->fresh();
    expect($fresh->cancelled_at)->not->toBeNull()
        ->and($fresh->cancelled_reason)->toBe('Duplicate purchase')
        ->and($fresh->cancelled_by)->toBe($this->user->id);
});
