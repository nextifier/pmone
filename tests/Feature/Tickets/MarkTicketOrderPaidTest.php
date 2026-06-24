<?php

use App\Enums\Ticketing\TicketOrderStatus;
use App\Jobs\Ticket\SendAttendeeETicketJob;
use App\Jobs\Ticket\SendTicketOrderConfirmationJob;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketOrderItem;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);

    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create([
        'project_id' => $this->project->id,
        'tickets_enabled' => true,
        'start_date' => now()->addMonth(),
        'location' => 'Sentul City',
    ]);
});

/**
 * A non-free, paid order stuck on PendingPayment with one attendee whose email
 * differs from the buyer (so confirmation + per-attendee e-ticket both dispatch).
 *
 * @param  array<string, mixed>  $orderOverrides
 * @return array{0: TicketOrder, 1: Attendee}
 */
function pendingOrderWithAttendee(Event $event, array $orderOverrides = []): array
{
    $ticket = Ticket::factory()->create(['event_id' => $event->id]);
    $order = TicketOrder::factory()->create(array_merge([
        'event_id' => $event->id,
        'buyer_email' => 'buyer@example.com',
        'subtotal' => 100000,
        'discount_amount' => 0,
        'total' => 100000,
        'status' => TicketOrderStatus::PendingPayment,
        'paid_at' => null,
    ], $orderOverrides));
    $item = TicketOrderItem::factory()->create([
        'ticket_order_id' => $order->id,
        'ticket_id' => $ticket->id,
        'quantity' => 1,
        'unit_price' => 100000,
        'subtotal' => 100000,
    ]);
    $attendee = Attendee::factory()->create([
        'ticket_order_item_id' => $item->id,
        'name' => 'Anton',
        'email' => 'anton@example.com',
        'qr_token' => Str::random(40),
    ]);

    return [$order, $attendee];
}

it('marks a pending order as paid and dispatches the confirmation + e-ticket', function () {
    Bus::fake();
    [$order] = pendingOrderWithAttendee($this->event);

    $this->postJson("/api/events/{$this->event->id}/ticket-orders/{$order->ulid}/mark-paid", [
        'payment_channel' => 'BCA',
    ])
        ->assertSuccessful()
        ->assertJsonPath('order.status', 'confirmed');

    $order->refresh();
    expect($order->status)->toBe(TicketOrderStatus::Confirmed)
        ->and($order->paid_at)->not->toBeNull()
        ->and($order->payment_channel)->toBe('BCA')
        ->and($order->marked_paid_manually_at)->not->toBeNull()
        ->and($order->marked_paid_by)->toBe($this->user->id);

    Bus::assertDispatched(SendTicketOrderConfirmationJob::class, fn ($j) => $j->ticketOrderId === $order->id);
    Bus::assertDispatched(SendAttendeeETicketJob::class);
});

it('normalizes the selected channel to uppercase', function () {
    Bus::fake();
    [$order] = pendingOrderWithAttendee($this->event);

    $this->postJson("/api/events/{$this->event->id}/ticket-orders/{$order->ulid}/mark-paid", [
        'payment_channel' => 'qris',
        'note' => 'Paid via QRIS, gateway webhook never arrived.',
    ])->assertSuccessful();

    expect($order->refresh()->payment_channel)->toBe('QRIS');
});

it('requires a supported payment channel', function () {
    [$order] = pendingOrderWithAttendee($this->event);

    $this->postJson("/api/events/{$this->event->id}/ticket-orders/{$order->ulid}/mark-paid")
        ->assertJsonValidationErrors(['payment_channel']);

    $this->postJson("/api/events/{$this->event->id}/ticket-orders/{$order->ulid}/mark-paid", [
        'payment_channel' => 'NOT_A_REAL_CHANNEL',
    ])->assertJsonValidationErrors(['payment_channel']);
});

it('logs the manual mark-paid activity', function () {
    Bus::fake();
    [$order] = pendingOrderWithAttendee($this->event);

    $this->postJson("/api/events/{$this->event->id}/ticket-orders/{$order->ulid}/mark-paid", [
        'payment_channel' => 'BCA',
    ])
        ->assertSuccessful();

    expect(
        Activity::query()
            ->where('event', 'ticket_order_marked_paid_manual')
            ->where('properties->ticket_order_id', $order->id)
            ->exists()
    )->toBeTrue();
});

it('422s when the order is not pending payment', function (TicketOrderStatus $status) {
    Bus::fake();
    [$order] = pendingOrderWithAttendee($this->event, [
        'status' => $status,
        'paid_at' => $status === TicketOrderStatus::Confirmed ? now() : null,
    ]);

    $this->postJson("/api/events/{$this->event->id}/ticket-orders/{$order->ulid}/mark-paid", [
        'payment_channel' => 'BCA',
    ])->assertStatus(422);

    Bus::assertNotDispatched(SendTicketOrderConfirmationJob::class);
    Bus::assertNotDispatched(SendAttendeeETicketJob::class);
})->with([
    'confirmed' => TicketOrderStatus::Confirmed,
    'cancelled' => TicketOrderStatus::Cancelled,
    'expired' => TicketOrderStatus::Expired,
]);

it('404s for an order from another event', function () {
    [$order] = pendingOrderWithAttendee($this->event);
    $other = Event::factory()->create(['project_id' => $this->project->id]);

    $this->postJson("/api/events/{$other->id}/ticket-orders/{$order->ulid}/mark-paid", [
        'payment_channel' => 'BCA',
    ])->assertNotFound();
});

it('forbids marking paid without the tickets.mark_paid permission', function () {
    [$order] = pendingOrderWithAttendee($this->event);
    $plain = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($plain)
        ->postJson("/api/events/{$this->event->id}/ticket-orders/{$order->ulid}/mark-paid")
        ->assertForbidden();
});

it('validates payment_channel and note length', function () {
    [$order] = pendingOrderWithAttendee($this->event);

    $this->postJson("/api/events/{$this->event->id}/ticket-orders/{$order->ulid}/mark-paid", [
        'payment_channel' => str_repeat('x', 51),
        'note' => str_repeat('y', 1001),
    ])->assertJsonValidationErrors(['payment_channel', 'note']);
});
