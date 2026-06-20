<?php

use App\Models\Attendee;
use App\Models\Event;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketPricePhase;
use App\Models\User;
use Database\Seeders\GlobalAiExpoAttendeesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seedGlobalAiExpoTickets(): Event
{
    $project = Project::factory()->create(['username' => 'globalaiexpo']);
    $event = Event::factory()->create(['project_id' => $project->id, 'is_active' => true]);

    foreach (['regular-ticket', 'vip-ticket', '3-day-bundle', 'conference-pass', 'meet-and-greet'] as $slug) {
        $ticket = Ticket::factory()->create(['event_id' => $event->id, 'slug' => $slug]);
        TicketPricePhase::factory()->create([
            'ticket_id' => $ticket->id,
            'price' => 100000,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);
    }

    return $event;
}

it('seeds named attendees across all ticket types', function () {
    User::factory()->create();
    $event = seedGlobalAiExpoTickets();

    $this->seed(GlobalAiExpoAttendeesSeeder::class);

    $attendees = Attendee::forEvent($event->id)->get();

    expect($attendees)->toHaveCount(42)
        ->and($attendees->whereNotNull('checked_in_at'))->not->toBeEmpty()
        ->and($attendees->every(fn ($a) => filled($a->name) && filled($a->qr_token)))->toBeTrue();
});

it('is idempotent across re-runs', function () {
    User::factory()->create();
    $event = seedGlobalAiExpoTickets();

    $this->seed(GlobalAiExpoAttendeesSeeder::class);
    $this->seed(GlobalAiExpoAttendeesSeeder::class);

    expect(Attendee::forEvent($event->id)->count())->toBe(42)
        ->and(TicketOrder::where('event_id', $event->id)
            ->where('batch_label', 'Seeder: Global AI Expo Attendees')
            ->count())->toBe(5);
});

it('skips gracefully when the event is absent', function () {
    $this->seed(GlobalAiExpoAttendeesSeeder::class);

    expect(Attendee::count())->toBe(0);
});
