<?php

use App\Enums\Ticketing\TicketOrderStatus;
use App\Jobs\Ticket\SendAttendeeETicketJob;
use App\Jobs\Ticket\SendTicketOrderConfirmationJob;
use App\Mail\Ticket\AttendeeETicketMail;
use App\Mail\Ticket\TicketOrderConfirmationMail;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventDay;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketOrderItem;
use App\Models\TicketPricePhase;
use App\Services\Ticket\TicketPurchaseService;
use App\Support\EventIcs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->project = Project::factory()->create(['email' => 'help@org.test']);
    $this->event = Event::factory()->create([
        'project_id' => $this->project->id,
        'tickets_enabled' => true,
        'title' => 'Global AI Expo 2026',
        'start_date' => now()->addMonths(3)->setTime(9, 0),
        'end_date' => now()->addMonths(3)->addDays(2)->setTime(18, 0),
        'location' => 'Sentul City, Bogor',
        'location_link' => 'https://maps.example/sentul',
        'timezone' => 'Asia/Jakarta',
    ]);
    $this->service = app(TicketPurchaseService::class);
});

function freeEntryTicket(Event $event): Ticket
{
    $ticket = Ticket::factory()->create(['event_id' => $event->id]);
    TicketPricePhase::factory()->create([
        'ticket_id' => $ticket->id,
        'price' => 0,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    return $ticket->load('pricePhases');
}

// --- ICS ---------------------------------------------------------------------

it('builds an ICS calendar entry for the event', function () {
    $ics = EventIcs::forEvent($this->event, 'https://example.test/order/abc');

    expect($ics)->toContain('BEGIN:VCALENDAR')
        ->and($ics)->toContain('BEGIN:VEVENT')
        ->and($ics)->toContain('SUMMARY:Global AI Expo 2026')
        ->and($ics)->toContain('DTSTART;TZID=Asia/Jakarta:')
        ->and($ics)->toContain('LOCATION:Sentul City\\, Bogor')
        ->and($ics)->toContain('END:VCALENDAR');
});

// --- Consolidation -----------------------------------------------------------

it('sends ONE consolidated e-ticket for a single-ticket self-purchase', function () {
    Mail::fake();
    $ticket = freeEntryTicket($this->event);

    $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'Anton',
        'buyer_email' => 'anton@example.com',
        'buyer_phone' => '0812',
        'also_attending' => true,
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ]);

    Mail::assertSent(AttendeeETicketMail::class, fn (AttendeeETicketMail $m) => $m->consolidated === true);
    Mail::assertNotSent(TicketOrderConfirmationMail::class);
});

it('sends ONE order confirmation (carrying the buyer QR) for a multi-ticket self-purchase', function () {
    Mail::fake();
    $ticket = freeEntryTicket($this->event);

    $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'Anton',
        'buyer_email' => 'anton@example.com',
        'buyer_phone' => '0812',
        'also_attending' => true,
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 2]],
    ]);

    // The attending buyer gets exactly one email - the order confirmation, which now
    // carries their own QR inline - never a separate e-ticket on top of it.
    Mail::assertSent(TicketOrderConfirmationMail::class, 1);
    Mail::assertSent(TicketOrderConfirmationMail::class, fn (TicketOrderConfirmationMail $m) => $m->buyerAttendee?->email === 'anton@example.com');
    Mail::assertNotSent(AttendeeETicketMail::class);
});

it('dispatches only the order confirmation (no separate e-ticket) for a multi-ticket self-purchase', function () {
    Bus::fake();
    $ticket = freeEntryTicket($this->event);

    $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'Anton',
        'buyer_email' => 'anton@example.com',
        'buyer_phone' => '0812',
        'also_attending' => true,
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 3]],
    ]);

    Bus::assertDispatched(SendTicketOrderConfirmationJob::class, 1);
    Bus::assertNotDispatched(SendAttendeeETicketJob::class);
});

it('emails the buyer once and e-tickets only the attendees with a distinct email', function () {
    Bus::fake();
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id]);
    $order = TicketOrder::factory()->create([
        'event_id' => $this->event->id,
        'buyer_email' => 'buyer@example.com',
        'subtotal' => 200000,
        'discount_amount' => 0,
        'total' => 200000,
        'status' => TicketOrderStatus::PendingPayment,
        'paid_at' => null,
    ]);
    $item = TicketOrderItem::factory()->create([
        'ticket_order_id' => $order->id,
        'ticket_id' => $ticket->id,
        'quantity' => 2,
        'unit_price' => 100000,
        'subtotal' => 200000,
    ]);
    // Attendee #1 is the buyer themselves; #2 is a friend with their own email.
    Attendee::factory()->create(['ticket_order_item_id' => $item->id, 'email' => 'buyer@example.com', 'qr_token' => Str::random(40)]);
    $friend = Attendee::factory()->create(['ticket_order_item_id' => $item->id, 'email' => 'friend@example.com', 'qr_token' => Str::random(40)]);

    $this->service->markAsConfirmed($order);

    Bus::assertDispatched(SendTicketOrderConfirmationJob::class, 1);
    // Only the friend gets a personal e-ticket - the buyer's own ticket rides on the confirmation.
    Bus::assertDispatched(SendAttendeeETicketJob::class, 1);
    Bus::assertDispatched(SendAttendeeETicketJob::class, fn (SendAttendeeETicketJob $j) => $j->attendeeId === $friend->id);
});

it('sends only the confirmation when the single buyer is not attending', function () {
    Mail::fake();
    $ticket = freeEntryTicket($this->event);

    $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'Anton',
        'buyer_email' => 'anton@example.com',
        'buyer_phone' => '0812',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ]);

    Mail::assertSent(TicketOrderConfirmationMail::class);
    Mail::assertNotSent(AttendeeETicketMail::class);
});

// --- Enriched content + subject ----------------------------------------------

it('renders an enriched e-ticket with event when/where, QR, order and a unique subject', function () {
    Mail::fake();
    $ticket = freeEntryTicket($this->event);
    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'Anton',
        'buyer_email' => 'anton@example.com',
        'buyer_phone' => '0812',
        'also_attending' => true,
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ]);
    $attendee = $order->attendees()->first()->load(['ticket', 'ticketOrderItem.ticketOrder.event.project']);

    $mail = new AttendeeETicketMail(
        $attendee,
        'https://event.test/tickets/'.$attendee->ulid,
        $this->event,
        null,
        'https://api.test/qr.png',
        'https://logo.test/logo.png',
        consolidated: true,
    );

    $mail->assertSeeInHtml($this->event->date_label, false);
    $mail->assertSeeInHtml('Sentul City, Bogor', false);
    $mail->assertSeeInHtml('data:image/png', false); // QR embedded inline, not the remote URL
    $mail->assertDontSeeInHtml('https://api.test/qr.png', false);
    $mail->assertSeeInHtml($order->order_number, false);

    $subject = $mail->envelope()->subject;
    expect($subject)->toContain('Anton')->toContain($order->order_number);

    expect($mail->attachments())->toHaveCount(1);
});

it('renders ticket benefits with a portable checkmark, not the unsupported &checkmark; entity', function () {
    Mail::fake();
    $ticket = freeEntryTicket($this->event);
    $ticket->update(['benefits' => ['Access to AI exhibition hall', 'Event tote bag']]);

    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'Anton',
        'buyer_email' => 'anton@example.com',
        'buyer_phone' => '0812',
        'also_attending' => true,
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ]);
    $attendee = $order->attendees()->first()->load(['ticket', 'ticketOrderItem.ticketOrder.event.project']);

    $mail = new AttendeeETicketMail($attendee, 'https://e.test/x', $this->event, null, 'https://qr', null, true);

    $mail->assertSeeInHtml('Access to AI exhibition hall', false);
    $mail->assertSeeInHtml('&#10003;', false);   // numeric ref dikirim apa adanya (view biasa, tanpa inliner)
    $mail->assertDontSeeInHtml('&checkmark;', false);
    $mail->assertDontSeeInHtml('&rarr;', false);  // partial when/where pakai &#8594;
});

it('sends from the organizer name and replies to the project email', function () {
    Mail::fake();
    $ticket = freeEntryTicket($this->event);
    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'Anton',
        'buyer_email' => 'anton@example.com',
        'buyer_phone' => '0812',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 2]],
    ]);
    $order->load('event.project');

    $env = (new TicketOrderConfirmationMail($order, 'https://x'))->envelope();

    expect($env->from?->name)->toBe($this->project->name)
        ->and($env->from?->address)->toBe(config('mail.from.address'))
        ->and(collect($env->replyTo)->first()?->address)->toBe($this->project->email);
});

it('shows the chosen day on the e-ticket when the ticket is day-bound', function () {
    Mail::fake();
    $ticket = freeEntryTicket($this->event);
    $day = EventDay::factory()->create([
        'event_id' => $this->event->id,
        'day_number' => 2,
        'date' => $this->event->start_date->copy()->addDay(),
    ]);
    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'Anton',
        'buyer_email' => 'anton@example.com',
        'buyer_phone' => '0812',
        'also_attending' => true,
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 1]],
    ]);
    $attendee = $order->attendees()->first();
    $attendee->ticketOrderItem->update(['selected_event_day_id' => $day->id]);
    $attendee->load(['ticket', 'ticketOrderItem.selectedEventDay', 'ticketOrderItem.ticketOrder.event.project']);

    $mail = new AttendeeETicketMail($attendee, 'https://e.test/x', $this->event, null, 'https://qr', null, true);

    $mail->assertSeeInHtml('Valid for', false);
    $mail->assertSeeInHtml($day->date->format('D, M j, Y'), false);
});

it('renders an enriched order confirmation with line items, total and receipt links', function () {
    Mail::fake();
    $ticket = freeEntryTicket($this->event);
    TicketPricePhase::query()->where('ticket_id', $ticket->id)->update(['price' => 50000]);
    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'Anton',
        'buyer_email' => 'anton@example.com',
        'buyer_phone' => '0812',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 2]],
    ]);
    $order->load(['event.project', 'items.ticket']);

    $mail = new TicketOrderConfirmationMail(
        $order,
        'https://event.test/order/abc',
        'https://logo.test/logo.png',
        'https://api.test/receipt.pdf',
        'https://api.test/invoice.pdf',
    );

    $mail->assertSeeInHtml($this->event->date_label, false);
    $mail->assertSeeInHtml('Sentul City, Bogor', false);
    $mail->assertSeeInHtml($ticket->title, false);
    $mail->assertSeeInHtml('Total paid', false);
    $mail->assertSeeInHtml('https://event.test/order/abc', false);
    $mail->assertSeeInHtml('https://api.test/receipt.pdf', false);

    expect($mail->attachments())->toHaveCount(1);
});

it('embeds the buyer QR inline in the order confirmation for a multi-ticket self-purchase', function () {
    Mail::fake();
    $ticket = freeEntryTicket($this->event);
    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'Anton',
        'buyer_email' => 'anton@example.com',
        'buyer_phone' => '0812',
        'also_attending' => true,
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 2]],
    ]);
    $order->load(['event.project', 'items.ticket', 'attendees.ticket']);

    $mail = TicketOrderConfirmationMail::for($order);

    expect($mail->buyerAttendee?->email)->toBe('anton@example.com');
    $mail->assertSeeInHtml('<img', false);
    $mail->assertSeeInHtml('data:image/png', false); // buyer QR embedded inline, not the remote URL
    $mail->assertSeeInHtml($order->order_number, false);
});

it('shows no QR on the order confirmation when the buyer is not attending', function () {
    Mail::fake();
    $ticket = freeEntryTicket($this->event);
    $order = $this->service->createOrder([
        'event_id' => $this->event->id,
        'buyer_name' => 'Anton',
        'buyer_email' => 'anton@example.com',
        'buyer_phone' => '0812',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => 2]],
    ]);
    $order->load(['event.project', 'items.ticket', 'attendees.ticket']);

    $mail = TicketOrderConfirmationMail::for($order);

    expect($mail->buyerAttendee)->toBeNull();
    $mail->assertDontSeeInHtml('data:image/png', false);
});
