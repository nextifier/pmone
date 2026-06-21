<?php

use App\Enums\Ticketing\TicketOrderStatus;
use App\Models\Event;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\TicketOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
});

/**
 * A pending ticket order plus an active Xendit gateway (session checkout) on its
 * project, linked to the order so the webhook can resolve the card brand.
 * Returns [order, webhookUrl, token].
 *
 * @return array{0: TicketOrder, 1: string, 2: string}
 */
function setupTicketSessionOrder(string $orderNumber = 'TIX-20260101-SESS'): array
{
    $token = 'test-callback-token';
    $project = Project::factory()->create();
    $event = Event::factory()->create(['project_id' => $project->id, 'tickets_enabled' => true]);
    $gateway = ProjectPaymentGateway::factory()->create([
        'project_id' => $project->id,
        'provider' => 'xendit',
        'mode' => 'test',
        'is_active' => true,
        'webhook_token' => $token,
        'checkout_method' => 'payment_link_sessions',
    ]);

    $order = TicketOrder::factory()->create([
        'event_id' => $event->id,
        'status' => TicketOrderStatus::PendingPayment,
        'order_number' => $orderNumber,
        'xendit_invoice_id' => 'ps-existing-session',
        'payment_gateway_id' => $gateway->id,
        'subtotal' => 1500000,
        'total' => 1500000,
    ]);

    return [$order, "/api/webhooks/xendit/{$project->username}", $token];
}

test('ticket session webhook confirms the order and stores the resolved card brand', function () {
    [$order, $url, $token] = setupTicketSessionOrder();

    // resolveSessionChannel fetches the underlying v3 payment request to read the
    // real card brand. Fake it as a Visa card payment.
    Http::fake([
        'api.xendit.co/v3/payment_requests/*' => Http::response([
            'channel_code' => 'CARDS',
            'channel_properties' => [
                'card_details' => ['network' => 'VISA'],
            ],
        ], 200),
    ]);

    $response = $this->postJson($url, [
        'event' => 'payment_session.completed',
        'data' => [
            'id' => 'ps-existing-session',
            'reference_id' => $order->order_number,
            'status' => 'COMPLETED',
            'amount' => 1500000,
            'payment_request_id' => 'pr-card-123',
        ],
    ], ['x-callback-token' => $token]);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Ticket order confirmed');

    $order->refresh();
    expect($order->status)->toBe(TicketOrderStatus::Confirmed);
    expect($order->payment_channel)->toBe('VISA');
    expect($order->paid_at)->not->toBeNull();
});

test('ticket session webhook maps a Mastercard payment to the MASTERCARD channel', function () {
    [$order, $url, $token] = setupTicketSessionOrder('TIX-20260101-MAST');

    Http::fake([
        'api.xendit.co/v3/payment_requests/*' => Http::response([
            'channel_code' => 'CARDS',
            'channel_properties' => [
                'card_details' => ['network' => 'MASTERCARD'],
            ],
        ], 200),
    ]);

    $this->postJson($url, [
        'event' => 'payment_session.completed',
        'data' => [
            'id' => 'ps-existing-session',
            'reference_id' => $order->order_number,
            'status' => 'COMPLETED',
            'payment_request_id' => 'pr-card-456',
        ],
    ], ['x-callback-token' => $token])->assertSuccessful();

    expect($order->fresh()->payment_channel)->toBe('MASTERCARD');
});

test('ticket session webhook expires the order on payment_session.expired', function () {
    [$order, $url, $token] = setupTicketSessionOrder('TIX-20260101-EXP');

    $response = $this->postJson($url, [
        'event' => 'payment_session.expired',
        'data' => [
            'id' => 'ps-existing-session',
            'reference_id' => $order->order_number,
            'status' => 'EXPIRED',
        ],
    ], ['x-callback-token' => $token]);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Ticket order expired');

    expect($order->fresh()->status)->toBe(TicketOrderStatus::Expired);
});
