<?php

use App\Enums\ReservationStatus;
use App\Enums\Ticketing\TicketOrderStatus;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\Reservation;
use App\Models\TicketOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();

    // Project A owns the callback token that authenticates the request.
    $this->projectA = Project::factory()->create(['status' => 'active']);
    ProjectPaymentGateway::factory()->for($this->projectA)->create([
        'webhook_token' => 'token-a',
        'is_active' => true,
    ]);
    $this->eventA = Event::factory()->withoutPaymentGateway()->create([
        'project_id' => $this->projectA->id,
    ]);

    // Project B is a different tenant whose orders must be untouchable with A's token.
    $this->projectB = Project::factory()->create(['status' => 'active']);
    ProjectPaymentGateway::factory()->for($this->projectB)->create([
        'webhook_token' => 'token-b',
        'is_active' => true,
    ]);
    $this->eventB = Event::factory()->withoutPaymentGateway()->create([
        'project_id' => $this->projectB->id,
    ]);
});

it('does not confirm another project ticket order with a valid token (cross-project)', function () {
    // Ticket order belongs to project B, but the webhook is posted to project A's
    // per-project URL with project A's valid callback token.
    $order = TicketOrder::factory()->create([
        'event_id' => $this->eventB->id,
        'order_number' => 'TIX-CROSS-1',
        'status' => TicketOrderStatus::PendingPayment,
        'total' => 100000,
    ]);

    $this->postJson(
        "/api/webhooks/xendit/{$this->projectA->username}",
        ['external_id' => 'TIX-CROSS-1', 'id' => 'inv_cross_1', 'status' => 'PAID', 'amount' => 100000],
        ['x-callback-token' => 'token-a']
    )->assertSuccessful()
        ->assertJsonPath('message', 'Reservation not found (acknowledged)');

    expect($order->fresh()->status)->toBe(TicketOrderStatus::PendingPayment);
});

it('confirms a same-project ticket order on an exact-amount webhook', function () {
    $order = TicketOrder::factory()->create([
        'event_id' => $this->eventA->id,
        'order_number' => 'TIX-SAME-1',
        'status' => TicketOrderStatus::PendingPayment,
        'total' => 100000,
    ]);

    $this->postJson(
        "/api/webhooks/xendit/{$this->projectA->username}",
        ['external_id' => 'TIX-SAME-1', 'id' => 'inv_same_1', 'status' => 'PAID', 'amount' => 100000],
        ['x-callback-token' => 'token-a']
    )->assertSuccessful()
        ->assertJsonPath('message', 'Ticket order confirmed');

    expect($order->fresh()->status)->toBe(TicketOrderStatus::Confirmed);
});

it('does not confirm a ticket order on an underpaid webhook', function () {
    $order = TicketOrder::factory()->create([
        'event_id' => $this->eventA->id,
        'order_number' => 'TIX-UNDER-1',
        'status' => TicketOrderStatus::PendingPayment,
        'total' => 1000000,
    ]);

    // Reports only 10,000 paid against a 1,000,000 order total.
    $this->postJson(
        "/api/webhooks/xendit/{$this->projectA->username}",
        ['external_id' => 'TIX-UNDER-1', 'id' => 'inv_under_1', 'status' => 'PAID', 'amount' => 10000],
        ['x-callback-token' => 'token-a']
    )->assertSuccessful()
        ->assertJsonPath('message', 'Payment amount mismatch (no action)');

    expect($order->fresh()->status)->toBe(TicketOrderStatus::PendingPayment);
});

it('confirms a ticket order when the paid amount is within the 1 IDR epsilon', function () {
    $order = TicketOrder::factory()->create([
        'event_id' => $this->eventA->id,
        'order_number' => 'TIX-EPS-1',
        'status' => TicketOrderStatus::PendingPayment,
        'total' => 100000,
    ]);

    // 99999.5 is short by 0.5 IDR — within the 1 IDR tolerance, so it settles.
    $this->postJson(
        "/api/webhooks/xendit/{$this->projectA->username}",
        ['external_id' => 'TIX-EPS-1', 'id' => 'inv_eps_1', 'status' => 'PAID', 'amount' => 99999.5],
        ['x-callback-token' => 'token-a']
    )->assertSuccessful()
        ->assertJsonPath('message', 'Ticket order confirmed');

    expect($order->fresh()->status)->toBe(TicketOrderStatus::Confirmed);
});

it('does not settle another project reservation with a valid token (cross-project)', function () {
    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->create([
        'event_id' => $this->eventB->id,
        'hotel_id' => $hotel->id,
        'status' => ReservationStatus::PendingPayment,
        'reservation_number' => 'HTL-CROSS-1',
        'xendit_invoice_id' => null,
    ]);

    $this->postJson(
        "/api/webhooks/xendit/{$this->projectA->username}",
        ['external_id' => 'HTL-CROSS-1', 'id' => 'inv_htl_cross_1', 'status' => 'PAID', 'payment_channel' => 'BCA'],
        ['x-callback-token' => 'token-a']
    )->assertSuccessful()
        ->assertJsonPath('message', 'Reservation not found (acknowledged)');

    expect($reservation->fresh()->status)->toBe(ReservationStatus::PendingPayment);
});
