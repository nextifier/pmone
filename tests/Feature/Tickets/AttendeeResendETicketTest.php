<?php

use App\Jobs\Ticket\SendAttendeeETicketJob;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPricePhase;
use App\Models\User;
use App\Services\Ticket\TicketPurchaseService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create(['project_id' => $this->project->id, 'tickets_enabled' => true]);
    $this->ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'max_quantity' => null]);
    TicketPricePhase::factory()->create([
        'ticket_id' => $this->ticket->id, 'price' => 0,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
    ]);

    $this->staff = User::factory()->create(['email_verified_at' => now()]);
    $this->staff->assignRole('staff');
});

function resendETicketAttendee(?string $email = 'attendee@example.com'): Attendee
{
    $order = app(TicketPurchaseService::class)->createOrder([
        'event_id' => test()->event->id,
        'buyer_name' => 'Buyer', 'buyer_email' => 'b@example.com', 'buyer_phone' => '0812',
        'items' => [['ticket_id' => test()->ticket->id, 'quantity' => 1]],
    ]);
    $attendee = $order->attendees()->first();
    $attendee->update(['email' => $email]);

    return $attendee;
}

it('resends an e-ticket to an attendee with an email', function () {
    Bus::fake();
    $attendee = resendETicketAttendee('jane@example.com');

    $this->actingAs($this->staff)
        ->postJson("/api/events/{$this->event->id}/attendees/{$attendee->id}/resend-eticket")
        ->assertOk();

    Bus::assertDispatched(SendAttendeeETicketJob::class, fn ($job) => $job->attendeeId === $attendee->id);
});

it('refuses to resend when the attendee has no email', function () {
    Bus::fake();
    $attendee = resendETicketAttendee(null);

    $this->actingAs($this->staff)
        ->postJson("/api/events/{$this->event->id}/attendees/{$attendee->id}/resend-eticket")
        ->assertStatus(422);

    Bus::assertNotDispatched(SendAttendeeETicketJob::class);
});

it('forbids non-staff from resending an e-ticket', function () {
    Bus::fake();
    $attendee = resendETicketAttendee();
    $outsider = User::factory()->create(['email_verified_at' => now()]);
    $outsider->assignRole('exhibitor');

    $this->actingAs($outsider)
        ->postJson("/api/events/{$this->event->id}/attendees/{$attendee->id}/resend-eticket")
        ->assertForbidden();
});

it('bulk resends only to attendees that have an email', function () {
    Bus::fake();
    $withEmail = resendETicketAttendee('a@example.com');
    $withoutEmail = resendETicketAttendee(null);

    $this->actingAs($this->staff)
        ->postJson("/api/events/{$this->event->id}/attendees/bulk-resend-eticket", [
            'ids' => [$withEmail->id, $withoutEmail->id],
        ])
        ->assertOk()
        ->assertJsonPath('sent_count', 1);

    Bus::assertDispatchedTimes(SendAttendeeETicketJob::class, 1);
});
