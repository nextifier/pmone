<?php

use App\Enums\Ticketing\TicketOrderStatus;
use App\Mail\Ticket\AttendeeETicketMail;
use App\Models\Event;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPricePhase;
use App\Models\TicketSession;
use App\Models\User;
use App\Services\Ticket\TicketPurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create(['project_id' => $this->project->id, 'tickets_enabled' => true]);
    $this->service = app(TicketPurchaseService::class);
});

function bulkTicket(Event $event, array $attrs = []): Ticket
{
    $ticket = Ticket::factory()->create(array_merge(['event_id' => $event->id, 'max_quantity' => null], $attrs));
    TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id,
        'price' => 50000,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    return $ticket;
}

it('generates N confirmed free attendees for an anonymous batch', function () {
    $ticket = bulkTicket($this->event);

    $order = $this->service->bulkGenerate([
        'event_id' => $this->event->id,
        'ticket_id' => $ticket->id,
        'mode' => 'anonymous',
        'quantity' => 5,
        'label_prefix' => 'VIP',
        'delivery' => 'generate_only',
        'batch_label' => 'Speakers',
    ])->fresh();

    expect($order->status)->toBe(TicketOrderStatus::Confirmed)
        ->and((float) $order->total)->toBe(0.0)
        ->and($order->source)->toBe('admin')
        ->and($order->batch_status)->toBe('completed')
        ->and($order->batch_label)->toBe('Speakers')
        ->and($order->attendees()->count())->toBe(5)
        ->and($order->attendees()->distinct('qr_token')->count('qr_token'))->toBe(5)
        ->and($order->attendees()->pluck('name')->all())->toContain('VIP #1', 'VIP #5');
});

it('uses just the prefix for a single anonymous ticket', function () {
    $ticket = bulkTicket($this->event);

    $order = $this->service->bulkGenerate([
        'event_id' => $this->event->id, 'ticket_id' => $ticket->id,
        'mode' => 'anonymous', 'quantity' => 1, 'label_prefix' => 'VIP', 'delivery' => 'generate_only',
    ]);

    expect($order->attendees()->first()->name)->toBe('VIP');
});

it('bypasses ticket and session stock caps', function () {
    $ticket = bulkTicket($this->event, ['stock' => 1, 'kind' => 'add_on']);
    $session = TicketSession::factory()->create(['ticket_id' => $ticket->id, 'capacity' => 2, 'is_active' => true]);

    $order = $this->service->bulkGenerate([
        'event_id' => $this->event->id, 'ticket_id' => $ticket->id, 'ticket_session_id' => $session->id,
        'mode' => 'anonymous', 'quantity' => 10, 'delivery' => 'generate_only',
    ]);

    expect($order->attendees()->count())->toBe(10)
        ->and($ticket->fresh()->sold_count)->toBe(0)
        ->and($session->fresh()->booked_count)->toBe(0);
});

it('uses recipient names verbatim and attaches users for emailed recipients', function () {
    $ticket = bulkTicket($this->event);

    $order = $this->service->bulkGenerate([
        'event_id' => $this->event->id, 'ticket_id' => $ticket->id, 'mode' => 'named',
        'recipients' => [['name' => 'Antonius', 'email' => 'a@x.com'], ['name' => 'Budi']],
        'delivery' => 'generate_only',
    ]);

    $attendees = $order->attendees()->orderBy('id')->get();

    expect($attendees->pluck('name')->all())->toBe(['Antonius', 'Budi'])
        ->and($attendees->firstWhere('email', 'a@x.com')->claimed_by_user_id)->not->toBeNull()
        ->and(User::where('email', 'a@x.com')->exists())->toBeTrue()
        ->and($attendees->firstWhere('name', 'Budi')->email)->toBeNull();
});

it('auto-emails each named recipient that has an email', function () {
    Mail::fake();
    $ticket = bulkTicket($this->event);

    $this->service->bulkGenerate([
        'event_id' => $this->event->id, 'ticket_id' => $ticket->id, 'mode' => 'named',
        'recipients' => [['name' => 'A', 'email' => 'a@x.com'], ['name' => 'B', 'email' => 'b@x.com'], ['name' => 'C']],
        'delivery' => 'auto_email',
    ]);

    Mail::assertSent(AttendeeETicketMail::class, 2);
});

it('does not auto-email on generate_only delivery', function () {
    Mail::fake();
    $ticket = bulkTicket($this->event);

    $this->service->bulkGenerate([
        'event_id' => $this->event->id, 'ticket_id' => $ticket->id, 'mode' => 'named',
        'recipients' => [['name' => 'A', 'email' => 'a@x.com']],
        'delivery' => 'generate_only',
    ]);

    Mail::assertNothingSent();
});
