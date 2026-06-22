<?php

use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventCustomField;
use App\Models\EventDay;
use App\Models\ExhibitorLead;
use App\Models\FieldResponse;
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
    User::factory()->create(['email_verified_at' => now()]);

    $project = Project::factory()->create(['username' => 'globalaiexpo']);
    $event = Event::factory()->create([
        'project_id' => $project->id,
        'is_active' => true,
        'tickets_enabled' => true,
        'start_date' => now()->addDays(30)->startOfDay(),
    ]);
    EventDay::factory()->create(['event_id' => $event->id, 'day_number' => 1, 'date' => now()->addDays(30)->startOfDay()]);

    foreach (['regular' => 50000, 'vip' => 250000, 'free-day' => 0] as $slug => $price) {
        $ticket = Ticket::factory()->create(['event_id' => $event->id, 'slug' => $slug, 'stock' => 500]);
        TicketPricePhase::factory()->create([
            'ticket_id' => $ticket->id,
            'price' => $price,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonths(2),
        ]);
    }

    return $event;
}

it('seeds a rich, varied demo dataset', function () {
    $event = seedGlobalAiExpoTickets();

    $this->seed(GlobalAiExpoAttendeesSeeder::class);

    expect(EventCustomField::where('event_id', $event->id)->where('settings->seeded', true)->count())->toBe(13)
        ->and(TicketOrder::where('event_id', $event->id)->count())->toBe(110)
        ->and(Attendee::forEvent($event->id)->count())->toBeGreaterThan(100)
        ->and(FieldResponse::query()->count())->toBeGreaterThan(0)
        ->and(ExhibitorLead::where('event_id', $event->id)->count())->toBeGreaterThan(0)
        ->and(User::where('email', 'like', '%@gae-demo.test')->count())->toBe(110);

    // Business matching auto-enables, check-ins exist, and statuses vary.
    expect($event->fresh()->business_matching_enabled)->toBeTrue()
        ->and(Attendee::forEvent($event->id)->whereNotNull('checked_in_at')->count())->toBeGreaterThan(0)
        ->and(TicketOrder::where('event_id', $event->id)->distinct()->pluck('status')->count())->toBeGreaterThan(1);

    // Orders are spread across the campaign (not all the same day).
    $days = TicketOrder::where('event_id', $event->id)->get()
        ->map(fn ($o) => $o->created_at->format('Y-m-d'))->unique();
    expect($days->count())->toBeGreaterThan(5);
});

it('is idempotent and never touches real public data', function () {
    $event = seedGlobalAiExpoTickets();
    $real = TicketOrder::factory()->create(['event_id' => $event->id, 'batch_label' => null]);

    $this->seed(GlobalAiExpoAttendeesSeeder::class);
    $this->seed(GlobalAiExpoAttendeesSeeder::class);

    expect(EventCustomField::where('event_id', $event->id)->where('settings->seeded', true)->count())->toBe(13)
        ->and(User::where('email', 'like', '%@gae-demo.test')->count())->toBe(110)
        ->and(TicketOrder::where('event_id', $event->id)->where('batch_label', 'Seeder: Global AI Expo Demo')->count())->toBe(110)
        ->and(TicketOrder::query()->whereKey($real->id)->exists())->toBeTrue();
});

it('skips gracefully when the event is absent', function () {
    $this->seed(GlobalAiExpoAttendeesSeeder::class);

    expect(Attendee::count())->toBe(0);
});
