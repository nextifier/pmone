<?php

use App\Enums\Ticketing\TicketOrderStatus;
use App\Jobs\Ticket\ExpireUnpaidTicketOrdersJob;
use App\Jobs\Ticket\SendTicketOrderConfirmationJob;
use App\Mail\Ticket\TicketOrderConfirmationMail;
use App\Models\Event;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Services\Ticket\TicketPurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create(['project_id' => $this->project->id, 'tickets_enabled' => true]);
});

it('expires unpaid orders past their payment window and releases held stock', function () {
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'stock' => 10]);
    $ticket->forceFill(['sold_count' => 2])->save();

    $order = TicketOrder::factory()->create([
        'event_id' => $this->event->id,
        'status' => TicketOrderStatus::PendingPayment,
        'payment_expires_at' => now()->subMinute(),
    ]);
    $order->items()->create(['ticket_id' => $ticket->id, 'quantity' => 2, 'unit_price' => 0, 'subtotal' => 0]);

    $count = (new ExpireUnpaidTicketOrdersJob)->handle(app(TicketPurchaseService::class));

    expect($count)->toBe(1)
        ->and($order->fresh()->status)->toBe(TicketOrderStatus::Expired)
        ->and($ticket->fresh()->sold_count)->toBe(0);
});

it('leaves orders whose payment window has not yet lapsed', function () {
    $order = TicketOrder::factory()->create([
        'event_id' => $this->event->id,
        'status' => TicketOrderStatus::PendingPayment,
        'payment_expires_at' => now()->addHour(),
    ]);

    (new ExpireUnpaidTicketOrdersJob)->handle(app(TicketPurchaseService::class));

    expect($order->fresh()->status)->toBe(TicketOrderStatus::PendingPayment);
});

it('sends the order confirmation email with a magic link', function () {
    Mail::fake();

    $order = TicketOrder::factory()->confirmed()->create([
        'event_id' => $this->event->id,
        'buyer_email' => 'buyer@example.com',
    ]);

    (new SendTicketOrderConfirmationJob($order->id))->handle();

    Mail::assertSent(TicketOrderConfirmationMail::class, fn ($mail) => $mail->hasTo('buyer@example.com'));
});
