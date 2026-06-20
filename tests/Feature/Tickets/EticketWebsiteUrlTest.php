<?php

use App\Jobs\Ticket\SendAttendeeETicketJob;
use App\Mail\Ticket\AttendeeETicketMail;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPricePhase;
use App\Services\Ticket\TicketPurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function eventWithWebsite(?string $website): Event
{
    $project = Project::factory()->create();
    if ($website !== null) {
        $project->links()->create(['label' => 'Website', 'url' => $website, 'order' => 1]);
    }

    return Event::factory()->create(['project_id' => $project->id, 'tickets_enabled' => true]);
}

function attendeeWithEmailForEvent(Event $event): Attendee
{
    $ticket = Ticket::factory()->create(['event_id' => $event->id, 'max_quantity' => null]);
    TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id, 'price' => 0,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
    ]);
    $order = app(TicketPurchaseService::class)->createOrder([
        'event_id' => $event->id,
        'buyer_name' => 'Buyer', 'buyer_email' => 'b@example.com', 'buyer_phone' => '0812',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ]);
    $attendee = $order->attendees()->first();
    $attendee->update(['email' => 'attendee@example.com']);

    return $attendee;
}

it('event publicBaseUrl prefers the project website link', function () {
    $event = eventWithWebsite('https://ev.example.test/');

    expect($event->load('project.links')->publicBaseUrl())->toBe('https://ev.example.test');
});

it('event publicBaseUrl falls back to the frontend url when no website link', function () {
    config(['app.frontend_url' => 'https://admin.example.test']);
    $event = eventWithWebsite(null);

    expect($event->load('project.links')->publicBaseUrl())->toBe('https://admin.example.test');
});

it('e-ticket email links to the event website when configured', function () {
    Mail::fake();
    $event = eventWithWebsite('https://ev.example.test');
    $attendee = attendeeWithEmailForEvent($event);

    (new SendAttendeeETicketJob($attendee->id))->handle();

    Mail::assertSent(
        AttendeeETicketMail::class,
        fn ($mail) => $mail->eticketUrl === "https://ev.example.test/tickets/{$attendee->ulid}"
    );
});

it('e-ticket email falls back to the frontend url with no website link', function () {
    config(['app.frontend_url' => 'https://admin.example.test']);
    Mail::fake();
    $event = eventWithWebsite(null);
    $attendee = attendeeWithEmailForEvent($event);

    (new SendAttendeeETicketJob($attendee->id))->handle();

    Mail::assertSent(
        AttendeeETicketMail::class,
        fn ($mail) => $mail->eticketUrl === "https://admin.example.test/tickets/{$attendee->ulid}"
    );
});
