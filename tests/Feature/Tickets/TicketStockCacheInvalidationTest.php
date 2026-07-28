<?php

use App\Jobs\PurgeEdgeCache;
use App\Models\Event;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPricePhase;
use App\Models\TicketSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\ResponseCache\Facades\ResponseCache;

uses(RefreshDatabase::class);

/**
 * Stock moves through `static::query()->update()` / `->decrement()` so that
 * concurrent buyers serialize on a row-level UPDATE instead of an application
 * lock (see Ticket::reserve). Those bypass Eloquent events, which meant
 * ClearsResponseCache never fired: the event website kept serving a /tickets
 * page built when the ticket was still available, for as long as its edge TTL.
 *
 * These tests lock the invalidation, not the arithmetic — AtomicInventoryTest
 * already covers the counters.
 */
beforeEach(function () {
    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create([
        'project_id' => $this->project->id,
        'tickets_enabled' => true,
    ]);
});

it('busts the tickets cache when stock is reserved', function () {
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'stock' => 5]);
    $spy = ResponseCache::spy();

    expect($ticket->reserve(2))->toBeTrue();

    $spy->shouldHaveReceived('clear')->with(['tickets']);
});

it('busts the tickets cache when stock is released', function () {
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'stock' => 5]);
    $ticket->reserve(3);
    $spy = ResponseCache::spy();

    $ticket->release(3);

    $spy->shouldHaveReceived('clear')->with(['tickets']);
});

it('leaves the cache alone when a reservation is refused', function () {
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'stock' => 1]);
    $ticket->reserve(1);
    $spy = ResponseCache::spy();

    expect($ticket->reserve(1))->toBeFalse();

    $spy->shouldNotHaveReceived('clear');
});

it('leaves the cache alone when a release would go negative', function () {
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'stock' => 5]);
    $spy = ResponseCache::spy();

    $ticket->release(3);

    $spy->shouldNotHaveReceived('clear');
});

it('busts the tickets cache for price phases and sessions too', function (string $model, array $attributes) {
    $record = $model::factory()->create($attributes);
    $spy = ResponseCache::spy();

    expect($record->reserve(1))->toBeTrue();

    $spy->shouldHaveReceived('clear')->with(['tickets']);
})->with([
    'price phase' => [TicketPricePhase::class, ['quota' => 3]],
    'session' => [TicketSession::class, ['capacity' => 3]],
]);

/**
 * The spy above replaces the container binding, so TagAwareResponseCache — the
 * decorator that turns a tag clear into a Cloudflare purge — never runs. This
 * one goes through the real binding to prove the edge purge is reached.
 */
it('queues an edge purge so the event website drops its cached /tickets page', function () {
    config(['edge-sites.token' => 'token-abc']);
    Queue::fake();

    $ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'stock' => 5]);
    $ticket->reserve(1);

    Queue::assertPushed(PurgeEdgeCache::class);
});

it('stays off the network when no edge purge token is configured', function () {
    config(['edge-sites.token' => null]);
    Queue::fake();

    $ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'stock' => 5]);
    $ticket->reserve(1);

    Queue::assertNotPushed(PurgeEdgeCache::class);
});
