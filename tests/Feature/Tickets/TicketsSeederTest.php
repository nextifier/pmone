<?php

use App\Http\Resources\PublicTicketResource;
use App\Models\Event;
use App\Models\EventDay;
use App\Models\Project;
use App\Models\Ticket;
use Database\Seeders\TicketsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake(config('media-library.disk_name'));

    $this->project = Project::factory()->create(['username' => 'globalaiexpo']);
    $this->event = Event::factory()->create([
        'project_id' => $this->project->id,
        'is_active' => true,
        'tickets_enabled' => true,
        'slug' => 'global-ai-expo-2026',
        'timezone' => 'Asia/Jakarta',
        'start_date' => '2026-11-20 09:00:00',
        'end_date' => '2026-11-22 21:00:00',
    ]);
});

it('seeds the Global AI Expo lineup with days, sessions, valid days and badge fields', function () {
    $this->seed(TicketsSeeder::class);

    $tickets = Ticket::where('event_id', $this->event->id)
        ->with(['validDays', 'sessions', 'pricePhases'])
        ->get();

    expect($tickets)->toHaveCount(5)
        ->and(EventDay::where('event_id', $this->event->id)->count())->toBe(3);

    $regular = $tickets->firstWhere('slug', 'regular-ticket');
    expect($regular->requires_day_selection)->toBeTrue()
        ->and($regular->validDays)->toHaveCount(3)
        ->and($regular->more_details['entrance'])->toBe('Regular entrance')
        ->and($regular->more_details['day_pass'])->toBe('1-day pass')
        ->and((float) $regular->pricePhases->first()->price)->toBe(350000.0);

    $bundle = $tickets->firstWhere('slug', '3-day-bundle');
    expect($bundle->requires_day_selection)->toBeFalse()
        ->and($bundle->validDays)->toHaveCount(3);

    $meetGreet = $tickets->firstWhere('slug', 'meet-and-greet');
    expect($meetGreet->kind->value)->toBe('add_on')
        ->and($meetGreet->sessions)->toHaveCount(3);

    $conference = $tickets->firstWhere('slug', 'conference-pass');
    expect($conference->kind->value)->toBe('add_on')
        ->and($conference->validDays)->toHaveCount(0);
});

it('exposes day_pass and entrance in the public ticket resource', function () {
    $this->seed(TicketsSeeder::class);

    $regular = Ticket::where('event_id', $this->event->id)
        ->where('slug', 'regular-ticket')
        ->with('pricePhases')
        ->first();

    $array = (new PublicTicketResource($regular))->toArray(request());

    expect($array['day_pass'])->toBe('1-day pass')
        ->and($array['entrance'])->toBe('Regular entrance');
});

it('is idempotent and keeps event day ids stable on re-run', function () {
    $this->seed(TicketsSeeder::class);
    $dayIds = EventDay::where('event_id', $this->event->id)->orderBy('day_number')->pluck('id')->all();
    $regularDays = Ticket::where('slug', 'regular-ticket')->first()->validDays()->pluck('event_days.id')->sort()->values()->all();

    $this->seed(TicketsSeeder::class);

    expect(Ticket::where('event_id', $this->event->id)->count())->toBe(5)
        ->and(EventDay::where('event_id', $this->event->id)->orderBy('day_number')->pluck('id')->all())->toBe($dayIds)
        ->and(Ticket::where('slug', 'regular-ticket')->first()->validDays()->pluck('event_days.id')->sort()->values()->all())->toBe($regularDays);
});
