<?php

use App\Enums\Ticketing\TicketOrderStatus;
use App\Models\Event;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketPricePhase;
use App\Models\TicketSession;
use App\Models\User;
use App\Services\Ticket\TicketPurchaseService;
use App\Services\Xendit\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create(['project_id' => $this->project->id, 'tickets_enabled' => true]);
    $this->service = app(TicketPurchaseService::class);
});

function entryTicketWithPrice(Event $event, float $price, ?int $stock = null): Ticket
{
    $ticket = Ticket::factory()->create(['event_id' => $event->id, 'stock' => $stock]);
    TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id,
        'price' => $price,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    return $ticket->load('pricePhases');
}

it('confirms a free order immediately and issues N attendees', function () {
    $ticket = entryTicketWithPrice($this->event, 0);

    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'Budi',
        'buyer_email' => 'budi@example.com',
        'buyer_phone' => '08123456789',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 3]],
    ]);

    expect($order->status)->toBe(TicketOrderStatus::Confirmed)
        ->and($order->isFree())->toBeTrue()
        ->and((float) $order->total)->toBe(0.0)
        ->and($order->attendees()->count())->toBe(3);

    // Placeholder names + unique tokens.
    $tokens = $order->attendees()->pluck('qr_token');
    expect($tokens->unique()->count())->toBe(3);
});

it('names extra attendees after the buyer when buying multiple', function () {
    $ticket = entryTicketWithPrice($this->event, 0);

    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'Antonius',
        'buyer_email' => 'antonius@example.com',
        'buyer_phone' => '0812',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 3]],
    ]);

    expect($order->attendees()->get()->sortBy('id')->pluck('name')->values()->all())
        ->toBe(['Antonius #1', 'Antonius #2', 'Antonius #3']);
});

it('uses just the buyer name for a single ticket', function () {
    $ticket = entryTicketWithPrice($this->event, 0);

    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'Antonius',
        'buyer_email' => 'antonius@example.com',
        'buyer_phone' => '0812',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ]);

    expect($order->attendees()->first()->name)->toBe('Antonius');
});

it('opens a payment for a paid order and stays pending', function () {
    ProjectPaymentGateway::factory()->create(['project_id' => $this->project->id, 'mode' => 'test', 'is_active' => true]);
    $ticket = entryTicketWithPrice($this->event, 60000);

    $xendit = mock(XenditService::class);
    $xendit->shouldReceive('createGenericInvoice')->once()
        ->andReturn(['invoice_id' => 'inv_123', 'invoice_url' => 'https://pay.example/inv_123']);

    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'Sari',
        'buyer_email' => 'sari@example.com',
        'buyer_phone' => '0811',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 2]],
    ], $xendit);

    expect($order->status)->toBe(TicketOrderStatus::PendingPayment)
        ->and((float) $order->total)->toBe(120000.0)
        ->and($order->payment_url)->toBe('https://pay.example/inv_123')
        ->and($order->xendit_invoice_id)->toBe('inv_123')
        ->and($order->attendees()->count())->toBe(2);
});

it('opens a Sessions checkout for a paid order when the gateway uses sessions', function () {
    ProjectPaymentGateway::factory()->create([
        'project_id' => $this->project->id,
        'mode' => 'test',
        'is_active' => true,
        'checkout_method' => 'payment_link_sessions',
    ]);
    $ticket = entryTicketWithPrice($this->event, 60000);

    $xendit = mock(XenditService::class);
    $xendit->shouldReceive('createTicketSession')->once()
        ->andReturn(['session_id' => 'ps_abc', 'payment_url' => 'https://pay.example/ps_abc', 'status' => 'ACTIVE']);

    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'Sari',
        'buyer_email' => 'sari@example.com',
        'buyer_phone' => '0811',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 2]],
    ], $xendit);

    expect($order->status)->toBe(TicketOrderStatus::PendingPayment)
        ->and($order->payment_url)->toBe('https://pay.example/ps_abc')
        ->and($order->xendit_invoice_id)->toBe('ps_abc');
});

it('uses the buyer data for attendee #1 when also attending', function () {
    $ticket = entryTicketWithPrice($this->event, 0);

    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'Dewi',
        'buyer_email' => 'dewi@example.com',
        'buyer_phone' => '0812',
        'also_attending' => true,
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 2]],
    ]);

    $first = $order->attendees()->get()->sortBy('id')->first();
    expect($first->name)->toBe('Dewi #1')
        ->and($first->personalized_at)->not->toBeNull()
        ->and($first->claimed_by_user_id)->not->toBeNull();
});

it('lazily registers the buyer and reuses an existing account by email', function () {
    $ticket = entryTicketWithPrice($this->event, 0);

    $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'New Buyer',
        'buyer_email' => 'newbuyer@example.com',
        'buyer_phone' => '0812',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ]);

    expect(User::where('email', 'newbuyer@example.com')->count())->toBe(1);

    $existing = User::factory()->create(['email' => 'existing@example.com']);
    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'Existing',
        'buyer_email' => 'existing@example.com',
        'buyer_phone' => '0812',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ]);

    expect(User::where('email', 'existing@example.com')->count())->toBe(1)
        ->and($order->user_id)->toBe($existing->id);
});

it('matches the buyer account case-insensitively instead of creating a duplicate', function () {
    $ticket = entryTicketWithPrice($this->event, 0);

    $existing = User::factory()->create(['email' => 'casebuyer@example.com']);

    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'Case Buyer',
        'buyer_email' => 'CaseBuyer@Example.com',
        'buyer_phone' => '0812',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ]);

    expect(User::withTrashed()->whereRaw('LOWER(email) = ?', ['casebuyer@example.com'])->count())->toBe(1)
        ->and($order->user_id)->toBe($existing->id);
});

it('stores a brand-new buyer email in lowercase', function () {
    $ticket = entryTicketWithPrice($this->event, 0);

    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'New Case',
        'buyer_email' => 'NewCase@Example.com',
        'buyer_phone' => '0812',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ]);

    expect($order->user->email)->toBe('newcase@example.com');
});

it('reuses and restores a soft-deleted buyer instead of hitting the unique email constraint', function () {
    $ticket = entryTicketWithPrice($this->event, 0);

    $deleted = User::factory()->create(['email' => 'activerow@example.com']);
    $deleted->delete();
    expect($deleted->fresh()->trashed())->toBeTrue();

    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'Anton',
        'buyer_email' => 'activerow@example.com',
        'buyer_phone' => '0812',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ]);

    expect(User::withTrashed()->where('email', 'activerow@example.com')->count())->toBe(1)
        ->and($order->user_id)->toBe($deleted->id)
        ->and($deleted->fresh()->trashed())->toBeFalse();
});

it('enforces stock and counts pending orders as held', function () {
    $ticket = entryTicketWithPrice($this->event, 0, stock: 2);

    $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'a@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 2]],
    ]);

    expect(fn () => $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'B', 'buyer_email' => 'b@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ]))->toThrow(HttpException::class);
});

it('rejects a ticket that is not currently on sale', function () {
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id]);
    TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id,
        'starts_at' => now()->addDays(5),
        'ends_at' => now()->addDays(10),
    ]);

    expect(fn () => $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'a@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ]))->toThrow(HttpException::class);
});

it('supports a mixed cart of entry and a sessioned add-on', function () {
    $entry = entryTicketWithPrice($this->event, 0);
    $addOn = Ticket::factory()->addOn()->create(['event_id' => $this->event->id]);
    TicketPricePhase::factory()->free()->create(['ticket_id' => $addOn->id, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
    $session = TicketSession::factory()->create(['ticket_id' => $addOn->id, 'capacity' => 10]);

    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'A', 'buyer_email' => 'a@example.com', 'buyer_phone' => '08',
        'items' => [
            ['ticket_id' => $entry->id, 'quantity' => 1],
            ['ticket_id' => $addOn->id, 'quantity' => 2, 'ticket_session_id' => $session->id],
        ],
    ]);

    expect($order->items()->count())->toBe(2)
        ->and($order->attendees()->count())->toBe(3)
        ->and($session->fresh()->booked_count)->toBe(2);
});

it('marks an order confirmed and expires a pending order releasing stock', function () {
    $ticket = entryTicketWithPrice($this->event, 60000, stock: 5);
    $order = TicketOrder::factory()->create(['event_id' => $this->event->id, 'status' => TicketOrderStatus::PendingPayment, 'total' => 60000]);
    $order->items()->create(['ticket_id' => $ticket->id, 'quantity' => 2, 'unit_price' => 60000, 'subtotal' => 120000]);
    $ticket->increment('sold_count', 2);

    $this->service->markAsConfirmed($order, ['payment_channel' => 'BCA']);
    expect($order->fresh()->status)->toBe(TicketOrderStatus::Confirmed);

    $pending = TicketOrder::factory()->create(['event_id' => $this->event->id, 'status' => TicketOrderStatus::PendingPayment]);
    $pending->items()->create(['ticket_id' => $ticket->id, 'quantity' => 1, 'unit_price' => 60000, 'subtotal' => 60000]);
    $ticket->increment('sold_count', 1);
    $before = $ticket->fresh()->sold_count;

    $this->service->expireOrder($pending);
    expect($pending->fresh()->status)->toBe(TicketOrderStatus::Expired)
        ->and($ticket->fresh()->sold_count)->toBe($before - 1);
});
