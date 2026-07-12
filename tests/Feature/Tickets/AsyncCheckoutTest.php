<?php

use App\Contracts\Payment\CreatesCheckout;
use App\Enums\Ticketing\TicketOrderStatus;
use App\Jobs\Ticket\CreateTicketCheckoutJob;
use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketPricePhase;
use App\Services\Ticket\TicketPurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create(['project_id' => $this->project->id, 'tickets_enabled' => true]);
    $this->service = app(TicketPurchaseService::class);
});

function asyncCheckoutTicket(Event $event, float $price): Ticket
{
    $ticket = Ticket::factory()->create(['event_id' => $event->id]);
    TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id,
        'price' => $price,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    return $ticket->load('pricePhases');
}

// ─── Step 1: createOrder() defers the gateway call ───────────────────────────

it('returns a paid order immediately with no payment_url and dispatches CreateTicketCheckoutJob', function () {
    Bus::fake();
    ProjectPaymentGateway::factory()->create(['project_id' => $this->project->id, 'mode' => 'test', 'is_active' => true]);
    $ticket = asyncCheckoutTicket($this->event, 60000);

    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'Sari', 'buyer_email' => 'sari@example.com', 'buyer_phone' => '0811',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 2]],
    ]);

    expect($order->status)->toBe(TicketOrderStatus::PendingPayment)
        ->and($order->payment_url)->toBeNull()
        ->and($order->xendit_invoice_id)->toBeNull()
        ->and((float) $order->total)->toBe(120000.0);

    Bus::assertDispatched(CreateTicketCheckoutJob::class, fn ($job) => $job->ticketOrderId === $order->id);
});

it('does not dispatch the checkout job for a free order', function () {
    Bus::fake();
    $ticket = asyncCheckoutTicket($this->event, 0);

    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'Budi', 'buyer_email' => 'budi@example.com', 'buyer_phone' => '0811',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ]);

    expect($order->status)->toBe(TicketOrderStatus::Confirmed);
    Bus::assertNotDispatched(CreateTicketCheckoutJob::class);
});

// ─── Step 2: the job body (TicketPurchaseService::openTicketCheckout) ────────

it('the job populates payment_url; a re-run is a no-op', function () {
    ProjectPaymentGateway::factory()->create(['project_id' => $this->project->id, 'mode' => 'test', 'is_active' => true]);
    $order = TicketOrder::factory()->create([
        'event_id' => $this->event->id,
        'status' => TicketOrderStatus::PendingPayment,
        'subtotal' => 60000,
        'total' => 60000,
    ]);

    $client = mock(CreatesCheckout::class);
    // ->once() is the assertion: a second openTicketCheckout() call below must
    // be a true no-op, or Mockery fails teardown verification.
    $client->shouldReceive('createCheckout')->once()
        ->andReturn(['reference' => 'ref_async', 'payment_url' => 'https://pay.example/ref_async', 'checkout_method' => 'payment_link_legacy']);
    $client->shouldReceive('gateway')->andReturnNull();

    $this->service->openTicketCheckout($order, $client);

    expect($order->fresh()->payment_url)->toBe('https://pay.example/ref_async')
        ->and($order->fresh()->xendit_invoice_id)->toBe('ref_async')
        ->and($order->fresh()->status)->toBe(TicketOrderStatus::PendingPayment);

    // Re-run (simulating a retried/duplicated job dispatch): payment_url is
    // already set, so createCheckout must not be invoked a second time.
    $this->service->openTicketCheckout($order->fresh(), $client);
});

it('openTicketCheckout is a no-op when the order is no longer PendingPayment', function () {
    $order = TicketOrder::factory()->confirmed()->create([
        'event_id' => $this->event->id,
        'subtotal' => 60000,
        'total' => 60000,
    ]);

    $client = mock(CreatesCheckout::class);
    $client->shouldNotReceive('createCheckout');

    $this->service->openTicketCheckout($order, $client);

    expect($order->fresh()->payment_url)->toBeNull();
});

it('the queued job swallows a gateway failure without throwing, leaving the order pending for retry', function () {
    // No active payment gateway configured for the project - openTicketCheckout()
    // throws a 422 inside the job; handle() must catch it (never let it escape
    // to the caller - Plan 017 explicitly avoids that under the sync queue
    // driver) and leave the order PendingPayment for a later retry / the
    // ExpireUnpaidTicketOrdersJob safety net to reclaim.
    $order = TicketOrder::factory()->create([
        'event_id' => $this->event->id,
        'status' => TicketOrderStatus::PendingPayment,
        'subtotal' => 60000,
        'total' => 60000,
    ]);

    (new CreateTicketCheckoutJob($order->id))->handle($this->service);

    expect($order->fresh()->status)->toBe(TicketOrderStatus::PendingPayment)
        ->and($order->fresh()->payment_url)->toBeNull();
});

// ─── Step 3: payment_status on the public order-status endpoint ─────────────

it('maps payment_status for preparing, ready, confirmed and failed orders', function () {
    ApiConsumer::factory()->create(['api_key' => 'pk_test_async']);
    $headers = ['X-API-Key' => 'pk_test_async'];

    $preparing = TicketOrder::factory()->create([
        'event_id' => $this->event->id, 'status' => TicketOrderStatus::PendingPayment, 'payment_url' => null,
    ]);
    $ready = TicketOrder::factory()->create([
        'event_id' => $this->event->id, 'status' => TicketOrderStatus::PendingPayment, 'payment_url' => 'https://pay.example/ready',
    ]);
    $confirmed = TicketOrder::factory()->confirmed()->create(['event_id' => $this->event->id]);
    $expired = TicketOrder::factory()->create(['event_id' => $this->event->id, 'status' => TicketOrderStatus::Expired]);

    $this->withHeaders($headers)->getJson("/api/public/ticket-orders/{$preparing->ulid}")
        ->assertOk()->assertJsonPath('data.payment_status', 'preparing');
    $this->withHeaders($headers)->getJson("/api/public/ticket-orders/{$ready->ulid}")
        ->assertOk()->assertJsonPath('data.payment_status', 'ready');
    $this->withHeaders($headers)->getJson("/api/public/ticket-orders/{$confirmed->ulid}")
        ->assertOk()->assertJsonPath('data.payment_status', 'confirmed');
    $this->withHeaders($headers)->getJson("/api/public/ticket-orders/{$expired->ulid}")
        ->assertOk()->assertJsonPath('data.payment_status', 'failed');
});
