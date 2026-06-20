<?php

use App\Enums\Ticketing\TicketKind;
use App\Enums\Ticketing\TicketOrderStatus;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventDay;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketOrderItem;
use App\Models\TicketPricePhase;
use App\Models\TicketSession;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('wires ticket relations to phases, sessions, valid days and event', function () {
    $event = Event::factory()->create();
    $ticket = Ticket::factory()->create(['event_id' => $event->id]);

    TicketPricePhase::factory()->count(2)->create(['ticket_id' => $ticket->id]);
    TicketSession::factory()->count(3)->create(['ticket_id' => $ticket->id]);
    $days = EventDay::factory()->count(2)->sequence(
        ['day_number' => 1, 'date' => '2026-04-11'],
        ['day_number' => 2, 'date' => '2026-04-12'],
    )->create(['event_id' => $event->id]);
    $ticket->validDays()->sync($days->pluck('id'));

    expect($ticket->pricePhases)->toHaveCount(2)
        ->and($ticket->sessions)->toHaveCount(3)
        ->and($ticket->validDays)->toHaveCount(2)
        ->and($ticket->event->is($event))->toBeTrue()
        ->and($event->tickets)->toHaveCount(1);
});

it('casts ticket kind to the TicketKind enum', function () {
    $ticket = Ticket::factory()->addOn()->create();

    expect($ticket->kind)->toBe(TicketKind::AddOn)
        ->and($ticket->isAddOn())->toBeTrue()
        ->and($ticket->isEntry())->toBeFalse();
});

it('chains order to items to attendees to scan logs', function () {
    $order = TicketOrder::factory()->create();
    $item = TicketOrderItem::factory()->create(['ticket_order_id' => $order->id]);
    $attendee = Attendee::factory()->create(['ticket_order_item_id' => $item->id]);

    expect($order->items)->toHaveCount(1)
        ->and($order->attendees)->toHaveCount(1)
        ->and($attendee->ticketOrderItem->is($item))->toBeTrue();
});

it('casts order status and derives free/confirmed helpers', function () {
    $free = TicketOrder::factory()->free()->create();
    $pending = TicketOrder::factory()->create(['total' => 60000]);

    expect($free->status)->toBe(TicketOrderStatus::Confirmed)
        ->and($free->isFree())->toBeTrue()
        ->and($free->isConfirmed())->toBeTrue()
        ->and($pending->isFree())->toBeFalse()
        ->and($pending->status)->toBe(TicketOrderStatus::PendingPayment);
});

it('auto-generates ulid, order_number and a hashed magic link on order create', function () {
    $order = TicketOrder::factory()->create();

    expect($order->ulid)->not->toBeNull()
        ->and($order->order_number)->toStartWith('TIX-')
        ->and($order->magic_link_token)->not->toBeNull()
        ->and($order->magicLinkRaw)->not->toBeNull()
        ->and($order->getRouteKeyName())->toBe('ulid');
});

it('auto-generates a qr_token for attendees', function () {
    $attendee = Attendee::factory()->create();

    expect($attendee->qr_token)->not->toBeNull()
        ->and($attendee->ulid)->not->toBeNull();
});
