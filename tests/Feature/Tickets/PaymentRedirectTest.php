<?php

use App\Models\Event;
use App\Models\Project;
use App\Models\Reservation;
use App\Models\TicketOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('bounces a ticket order payment return to the origin tickets result page', function () {
    $event = Event::factory()->create();
    $order = TicketOrder::factory()->confirmed()->create([
        'event_id' => $event->id,
        'return_origin' => 'http://localhost:3001',
    ]);

    $token = TicketOrder::magicLinkTokenFor($order->order_number);

    $this->get('/payment/redirect?order_id='.$order->order_number.'&result=success')
        ->assertRedirect("http://localhost:3001/tickets/result?ref={$order->order_number}&token={$token}");
});

it('bounces a failed ticket payment to the tickets page with a failed flag', function () {
    $order = TicketOrder::factory()->create(['return_origin' => 'http://localhost:3001']);

    $this->get('/payment/redirect?order_id='.$order->order_number.'&result=failed')
        ->assertRedirect("http://localhost:3001/tickets?failed={$order->order_number}");
});

it('honours the retry ~N suffix on the ticket order_id', function () {
    $order = TicketOrder::factory()->confirmed()->create(['return_origin' => 'http://localhost:3001']);

    $this->get('/payment/redirect?order_id='.$order->order_number.'~2&result=success')
        ->assertRedirectContains("/tickets/result?ref={$order->order_number}");
});

it('still routes hotel reservations to the hotels success page (regression)', function () {
    $project = Project::factory()->create();
    $event = Event::factory()->create(['project_id' => $project->id]);
    $reservation = Reservation::factory()->create([
        'event_id' => $event->id,
        'return_origin' => 'http://localhost:3002',
    ]);

    $this->get('/payment/redirect?order_id='.$reservation->reservation_number.'&result=success')
        ->assertRedirectContains('http://localhost:3002/hotels/success?ref='.$reservation->reservation_number);
});
