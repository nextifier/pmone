<?php

use App\Models\Event;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($this->user);

    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create(['project_id' => $this->project->id]);

    $this->conjunctions = Event::factory()->count(3)->create(['project_id' => $this->project->id]);
    foreach ($this->conjunctions as $index => $conjunction) {
        $this->event->conjunctionEvents()->attach($conjunction->id, ['order_column' => $index + 1]);
    }

    $this->apiBase = "/api/projects/{$this->project->username}/events/{$this->event->slug}/conjunctions";
});

it('reorders conjunction events and persists the new pivot order', function () {
    $reversed = $this->conjunctions->pluck('id')->reverse()->values()->all();

    $this->postJson("{$this->apiBase}/reorder", ['order' => $reversed])
        ->assertSuccessful();

    foreach ($reversed as $index => $eventId) {
        expect((int) $this->event->conjunctionEvents()->find($eventId)->pivot->order_column)
            ->toBe($index + 1);
    }
});

it('returns conjunction events ordered by the persisted order_column', function () {
    $reversed = $this->conjunctions->pluck('id')->reverse()->values()->all();

    $this->postJson("{$this->apiBase}/reorder", ['order' => $reversed])->assertSuccessful();

    $this->getJson($this->apiBase)
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $reversed[0])
        ->assertJsonPath('data.2.id', $reversed[2]);
});

it('validates that order is a required array of existing event ids', function () {
    $this->postJson("{$this->apiBase}/reorder", ['order' => 'not-an-array'])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('order');

    $this->postJson("{$this->apiBase}/reorder", ['order' => [999999]])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('order.0');
});

it('orders available events the same way as the /events page and exposes posters', function () {
    Storage::fake('public');

    // The 3 conjunctions seeded in beforeEach are draft, so they are excluded
    // (available() only returns published events not already linked).
    $ongoing = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'start_date' => now()->subDay(),
        'end_date' => now()->addDay(),
    ]);
    $upcoming = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'start_date' => now()->addDays(5),
        'end_date' => now()->addDays(7),
    ]);
    $completed = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'start_date' => now()->subDays(30),
        'end_date' => now()->subDays(28),
    ]);
    $noDate = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'start_date' => null,
        'end_date' => null,
    ]);

    $ongoing->addMedia(UploadedFile::fake()->image('poster.png', 400, 500))
        ->toMediaCollection('poster_image');

    $response = $this->getJson("{$this->apiBase}/available")
        ->assertSuccessful()
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('data.0.id', $ongoing->id)
        ->assertJsonPath('data.1.id', $upcoming->id)
        ->assertJsonPath('data.2.id', $completed->id)
        ->assertJsonPath('data.3.id', $noDate->id);

    expect($response->json('data.0.poster_image.sm'))->toBeString();
})->skip(
    fn () => DB::connection()->getDriverName() !== 'pgsql',
    'available() ordering uses PostgreSQL-specific EXTRACT(EPOCH ...); the test DB is SQLite.'
);

it('exposes date_label and location instead of project identifiers', function () {
    $linked = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'location' => 'Jakarta Convention Center',
        'start_date' => now()->addMonth(),
        'end_date' => now()->addMonth()->addDays(2),
    ]);
    $this->event->conjunctionEvents()->attach($linked->id, ['order_column' => 99]);

    $row = collect($this->getJson($this->apiBase)->assertSuccessful()->json('data'))
        ->firstWhere('id', $linked->id);

    expect($row['location'])->toBe('Jakarta Convention Center')
        ->and($row['date_label'])->toBe($linked->fresh()->date_label)
        ->and($row)->not->toHaveKey('project_name')
        ->and($row)->not->toHaveKey('project_username');
});

it('exposes the poster image for linked conjunction events', function () {
    Storage::fake('public');

    $this->event->conjunctionEvents()->find($this->conjunctions->first()->id)
        ->addMedia(UploadedFile::fake()->image('poster.png', 400, 500))
        ->toMediaCollection('poster_image');

    $response = $this->getJson($this->apiBase)->assertSuccessful();

    $linked = collect($response->json('data'))->firstWhere('id', $this->conjunctions->first()->id);
    expect($linked['poster_image']['sm'])->toBeString();
});
