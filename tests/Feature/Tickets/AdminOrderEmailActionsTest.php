<?php

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

function confirmedOrderWithAttendee(Event $event): array
{
    $ticket = Ticket::factory()->create(['event_id' => $event->id]);
    $order = TicketOrder::factory()->confirmed()->create([
        'event_id' => $event->id,
        'buyer_email' => 'buyer@example.com',
        'subtotal' => 100000,
        'discount_amount' => 0,
        'total' => 100000,
    ]);
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

it('resends the order confirmation to the buyer', function () {
    Bus::fake();
    [$order] = confirmedOrderWithAttendee($this->event);

    $this->postJson("/api/events/{$this->event->id}/ticket-orders/{$order->ulid}/resend-confirmation")
        ->assertSuccessful();

    Bus::assertDispatched(SendTicketOrderConfirmationJob::class, fn ($j) => $j->ticketOrderId === $order->id);
});

it('422s resend when the order has no buyer email', function () {
    [$order] = confirmedOrderWithAttendee($this->event);
    $order->update(['buyer_email' => null]);

    $this->postJson("/api/events/{$this->event->id}/ticket-orders/{$order->ulid}/resend-confirmation")
        ->assertStatus(422);
});

it('renders the confirmation email preview as HTML', function () {
    [$order] = confirmedOrderWithAttendee($this->event);

    $res = $this->get("/api/events/{$this->event->id}/ticket-orders/{$order->ulid}/preview-confirmation");

    $res->assertSuccessful();
    expect($res->headers->get('Content-Type'))->toContain('text/html');
    $res->assertSee($order->order_number, false);
    $res->assertSee('Your tickets are ready', false);
    $res->assertSee('Sentul City', false);
});

it('renders the attendee e-ticket preview as HTML', function () {
    [, $attendee] = confirmedOrderWithAttendee($this->event);

    $this->get("/api/events/{$this->event->id}/attendees/{$attendee->id}/preview-eticket")
        ->assertSuccessful()
        ->assertSee("Here's your ticket", false);
});

it('404s preview for an order from another event', function () {
    [$order] = confirmedOrderWithAttendee($this->event);
    $other = Event::factory()->create(['project_id' => $this->project->id]);

    $this->get("/api/events/{$other->id}/ticket-orders/{$order->ulid}/preview-confirmation")
        ->assertNotFound();
});

it('forbids resend without the attendees.update permission', function () {
    [$order] = confirmedOrderWithAttendee($this->event);
    $plain = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($plain)
        ->postJson("/api/events/{$this->event->id}/ticket-orders/{$order->ulid}/resend-confirmation")
        ->assertForbidden();
});
