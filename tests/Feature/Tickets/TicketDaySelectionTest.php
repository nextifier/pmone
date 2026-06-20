<?php

use App\Models\Event;
use App\Models\EventDay;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketPricePhase;
use App\Services\Ticket\ScanService;
use App\Services\Ticket\TicketPurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create(['project_id' => $this->project->id, 'tickets_enabled' => true]);
    $this->service = app(TicketPurchaseService::class);

    $this->days = collect(range(1, 4))->map(fn ($n) => EventDay::factory()->create([
        'event_id' => $this->event->id,
        'day_number' => $n,
        'date' => now()->addDays($n)->toDateString(),
    ]));

    // A "Day Pass": valid on all 4 days, but the buyer must pick one.
    $this->dayPass = Ticket::factory()->create([
        'event_id' => $this->event->id,
        'requires_day_selection' => true,
        'stock' => null,
        'max_quantity' => null,
    ]);
    $this->dayPass->validDays()->sync($this->days->pluck('id')->all());
    TicketPricePhase::factory()->create([
        'ticket_id' => $this->dayPass->id,
        'price' => 60000,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);
});

function placeDayOrder(int $ticketId, ?int $dayId): TicketOrder
{
    return test()->service->createOrder([
        'event_id' => test()->event->id,
        'buyer_name' => 'B',
        'buyer_email' => 'b@e.com',
        'buyer_phone' => '08',
        'items' => [array_filter([
            'ticket_id' => $ticketId,
            'quantity' => 1,
            'selected_event_day_id' => $dayId,
        ], fn ($v) => $v !== null)],
    ]);
}

it('stores the chosen day on the order item', function () {
    $day2 = $this->days->firstWhere('day_number', 2);

    $order = placeDayOrder($this->dayPass->id, $day2->id);

    expect($order->items->first()->selected_event_day_id)->toBe($day2->id);
});

it('requires a day to be chosen for a day pass', function () {
    expect(fn () => placeDayOrder($this->dayPass->id, null))
        ->toThrow(HttpException::class);
});

it('rejects a day that is not one of the ticket valid days', function () {
    $otherEvent = Event::factory()->create(['project_id' => $this->project->id]);
    $strayDay = EventDay::factory()->create(['event_id' => $otherEvent->id, 'day_number' => 1]);

    expect(fn () => placeDayOrder($this->dayPass->id, $strayDay->id))
        ->toThrow(HttpException::class);
});

it('does not require a day for a multi-day bundle', function () {
    $bundle = Ticket::factory()->create([
        'event_id' => $this->event->id,
        'requires_day_selection' => false,
        'stock' => null,
    ]);
    $bundle->validDays()->sync($this->days->pluck('id')->all());
    TicketPricePhase::factory()->create([
        'ticket_id' => $bundle->id,
        'price' => 200000,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    $order = placeDayOrder($bundle->id, null);

    expect($order->items->first()->selected_event_day_id)->toBeNull()
        ->and((float) $order->total)->toBe(200000.0);
});

it('narrows a day pass to only the chosen day at scan time', function () {
    // A free day pass confirms immediately, so its attendee is scannable.
    $freePass = Ticket::factory()->create(['event_id' => $this->event->id, 'requires_day_selection' => true, 'stock' => null]);
    $freePass->validDays()->sync($this->days->pluck('id')->all());
    TicketPricePhase::factory()->free()->create(['ticket_id' => $freePass->id, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);

    $day1 = $this->days->firstWhere('day_number', 1);
    $day2 = $this->days->firstWhere('day_number', 2);

    $chosenDay2 = placeDayOrder($freePass->id, $day2->id)->items->first()->attendees->first();
    $chosenDay2Again = placeDayOrder($freePass->id, $day2->id)->items->first()->attendees->first();

    $scan = app(ScanService::class);

    // Scanned on its chosen day (Day 2): valid, no warning.
    Carbon::setTestNow(Carbon::parse($day2->date)->setHour(10));
    $onDay2 = $scan->checkIn($chosenDay2->qr_token, $this->event->fresh(), 1, (string) Str::uuid());
    expect($onDay2['result'])->toBe('checked_in')
        ->and($onDay2['warning'] ?? null)->toBeNull();

    // Scanned on Day 1 - a valid day of the TICKET but NOT the chosen day - warns.
    // Without day narrowing this would pass cleanly (Day 1 is in valid_days).
    Carbon::setTestNow(Carbon::parse($day1->date)->setHour(10));
    $onDay1 = $scan->checkIn($chosenDay2Again->qr_token, $this->event->fresh(), 1, (string) Str::uuid());
    expect($onDay1['warning'])->toBe('cross_day');

    Carbon::setTestNow();
});
