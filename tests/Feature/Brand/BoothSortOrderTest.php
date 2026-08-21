<?php

use App\Imports\BrandEventsImport;
use App\Models\ApiConsumer;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->project = Project::factory()->create(['status' => 'active', 'username' => 'booth-order']);
    $this->event = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'slug' => 'booth-order-event',
        'is_active' => true,
    ]);
});

/**
 * Brand events for this event, created in an order that is deliberately not the
 * booth order, so a passing assertion can only come from the sort key.
 *
 * @param  array<int, string|null>  $boothNumbers
 * @return array<string, BrandEvent>
 */
function seedBooths(Event $event, array $boothNumbers): array
{
    $created = [];

    foreach ($boothNumbers as $index => $booth) {
        $brand = Brand::factory()->create(['name' => 'Brand '.($index + 1)]);
        $created[$booth ?? 'none'] = BrandEvent::factory()->create([
            'brand_id' => $brand->id,
            'event_id' => $event->id,
            'booth_number' => $booth,
            'status' => 'active',
        ]);
    }

    return $created;
}

it('fills the sort key when a brand event is created', function () {
    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => Brand::factory()->create()->id,
        'event_id' => $this->event->id,
        'booth_number' => 'cb001',
    ]);

    // Normalisation runs first, so the key is built from the canonical form.
    expect($brandEvent->booth_number)->toBe('CB-001')
        ->and($brandEvent->booth_sort_key)->toBe('CB000001');
});

it('recomputes the sort key when the booth number changes', function () {
    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => Brand::factory()->create()->id,
        'event_id' => $this->event->id,
        'booth_number' => 'A-1',
    ]);

    $brandEvent->update(['booth_number' => 'A-10']);

    expect($brandEvent->fresh()->booth_sort_key)->toBe('A000010');
});

it('leaves the sort key null when there is no booth number', function () {
    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => Brand::factory()->create()->id,
        'event_id' => $this->event->id,
        'booth_number' => null,
    ]);

    expect($brandEvent->booth_sort_key)->toBeNull();
});

it('ignores a sort key passed through mass assignment', function () {
    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => Brand::factory()->create()->id,
        'event_id' => $this->event->id,
        'booth_number' => 'B-2',
        'booth_sort_key' => 'HAND-WRITTEN',
    ]);

    expect($brandEvent->booth_sort_key)->toBe('B000002');
});

it('fills the sort key through the brand events import', function () {
    $import = new BrandEventsImport($this->event->id);

    $import->model([
        'brand_name' => 'Imported Brand',
        'booth_number' => 'cb002',
    ]);

    $brandEvent = BrandEvent::where('event_id', $this->event->id)->firstOrFail();

    expect($brandEvent->booth_number)->toBe('CB-002')
        ->and($brandEvent->booth_sort_key)->toBe('CB000002');
});

it('orders brand events by booth, not by when they were entered', function () {
    seedBooths($this->event, ['CB-002', 'A-10', 'CB-001', 'A-2', 'A-1']);

    $order = $this->event->brandEvents()->pluck('booth_number')->all();

    expect($order)->toBe(['A-1', 'A-2', 'A-10', 'CB-001', 'CB-002']);
});

it('keeps brand events sharing a booth number next to each other', function () {
    seedBooths($this->event, ['CB-001', 'CB-002', 'A-01', 'CB-002', 'CB-001']);

    $order = $this->event->brandEvents()->pluck('booth_number')->all();

    expect($order)->toBe(['A-01', 'CB-001', 'CB-001', 'CB-002', 'CB-002']);
});

it('sinks brand events without a booth number to the bottom', function () {
    seedBooths($this->event, [null, 'B-01', 'A-01']);

    $order = $this->event->brandEvents()->pluck('booth_number')->all();

    expect($order)->toBe(['A-01', 'B-01', null]);
});

it('orders the admin brand list by booth', function () {
    foreach (['brands.read'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web'])->syncPermissions(Permission::all());

    $user = User::factory()->create();
    $user->assignRole('master');
    $this->actingAs($user);

    seedBooths($this->event, ['A-10', 'A-2', 'A-1']);

    $response = $this
        ->getJson("/api/projects/{$this->project->username}/events/{$this->event->slug}/brands?client_only=true")
        ->assertSuccessful();

    expect(collect($response->json('data'))->pluck('booth_number')->all())
        ->toBe(['A-1', 'A-2', 'A-10']);

    // The key travels with the row so the client-side table can sort the same way.
    expect($response->json('data.0.booth_sort_key'))->toBe('A000001');
});

it('orders the public brand list by booth', function () {
    ApiConsumer::factory()->create(['api_key' => 'pk_test_booth_order', 'is_active' => true]);

    seedBooths($this->event, ['A-10', 'A-2', 'A-1']);

    $response = $this->getJson(
        "/api/public/projects/{$this->project->username}/events/{$this->event->slug}/brands",
        ['X-API-Key' => 'pk_test_booth_order'],
    )->assertSuccessful();

    expect(collect($response->json('data'))->pluck('booth_number')->all())
        ->toBe(['A-1', 'A-2', 'A-10']);
});

it('orders the brand events sheet by booth', function () {
    config(['services.sheets.api_token' => 'test-sheets-token']);

    seedBooths($this->event, ['A-10', 'A-2', 'A-1']);

    $response = $this->getJson('/api/sheets/brand-events?token=test-sheets-token')->assertSuccessful();

    $boothCol = array_search('Booth Number', $response->json('headings'), true);

    expect(collect($response->json('rows'))->pluck($boothCol)->all())
        ->toBe(['A-1', 'A-2', 'A-10']);
});

/**
 * An exhibitor holding one brand per booth. Written brand-first on purpose:
 * this is exactly the shape that used to come back in `brand_user` order.
 */
function exhibitorWithBooths(Event $event, array $boothNumbers): User
{
    $exhibitor = User::factory()->create(['email_verified_at' => now()]);
    Role::firstOrCreate(['name' => 'exhibitor', 'guard_name' => 'web']);
    $exhibitor->assignRole('exhibitor');

    foreach ($boothNumbers as $index => $booth) {
        $brand = Brand::factory()->create(['name' => 'Brand '.($index + 1)]);
        $brand->users()->attach($exhibitor->id);

        BrandEvent::factory()->create([
            'brand_id' => $brand->id,
            'event_id' => $event->id,
            'booth_number' => $booth,
            'status' => 'active',
        ]);
    }

    return $exhibitor;
}

it('orders the exhibitor dashboard by booth across brands', function () {
    $exhibitor = exhibitorWithBooths($this->event, ['A-10', 'A-2', 'A-1']);

    $response = $this->actingAs($exhibitor)
        ->getJson('/api/exhibitor/dashboard')
        ->assertSuccessful();

    expect(collect($response->json('data.brand_events'))->pluck('booth_number')->all())
        ->toBe(['A-1', 'A-2', 'A-10']);
});

it('keeps brands sharing a booth next to each other on the dashboard', function () {
    // The second CB-002 is added last, which is the reported bug: entered late,
    // so it used to land at the bottom instead of beside its twin.
    $exhibitor = exhibitorWithBooths($this->event, ['CB-001', 'CB-002', '6A-01', '6E-05', 'CB-002']);

    $response = $this->actingAs($exhibitor)
        ->getJson('/api/exhibitor/dashboard')
        ->assertSuccessful();

    expect(collect($response->json('data.brand_events'))->pluck('booth_number')->all())
        ->toBe(['6A-01', '6E-05', 'CB-001', 'CB-002', 'CB-002']);
});

it('groups the dashboard by event newest first', function () {
    $older = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'slug' => 'older-event',
        'is_active' => true,
        'start_date' => now()->subYear(),
        'end_date' => now()->subYear()->addDays(3),
    ]);
    $this->event->update([
        'start_date' => now()->addMonth(),
        'end_date' => now()->addMonth()->addDays(3),
    ]);

    $exhibitor = User::factory()->create(['email_verified_at' => now()]);
    Role::firstOrCreate(['name' => 'exhibitor', 'guard_name' => 'web']);
    $exhibitor->assignRole('exhibitor');

    // Attached older-event-first so insertion order is the opposite of the
    // expected output.
    foreach ([[$older, 'B-01'], [$this->event, 'B-01']] as [$event, $booth]) {
        $brand = Brand::factory()->create();
        $brand->users()->attach($exhibitor->id);
        BrandEvent::factory()->create([
            'brand_id' => $brand->id,
            'event_id' => $event->id,
            'booth_number' => $booth,
            'status' => 'active',
        ]);
    }

    $response = $this->actingAs($exhibitor)
        ->getJson('/api/exhibitor/dashboard')
        ->assertSuccessful();

    expect(collect($response->json('data.brand_events'))->pluck('event.slug')->all())
        ->toBe(['booth-order-event', 'older-event']);
});

it('names the other brands sharing a booth', function () {
    $exhibitor = exhibitorWithBooths($this->event, ['CB-002', 'A-01']);

    // A brand this exhibitor does not own, on the same booth.
    $outsider = Brand::factory()->create(['name' => 'Outsider Co']);
    BrandEvent::factory()->create([
        'brand_id' => $outsider->id,
        'event_id' => $this->event->id,
        'booth_number' => 'CB-002',
        'status' => 'active',
    ]);

    $response = $this->actingAs($exhibitor)
        ->getJson('/api/exhibitor/dashboard')
        ->assertSuccessful();

    $byBooth = collect($response->json('data.brand_events'))->keyBy('booth_number');

    expect($byBooth['CB-002']['booth_shared_with'])->toBe(['Outsider Co'])
        ->and($byBooth['A-01']['booth_shared_with'])->toBe([]);
});

it('orders brands within an event by booth on my events', function () {
    $exhibitor = exhibitorWithBooths($this->event, ['A-10', 'A-2', 'A-1']);

    $response = $this->actingAs($exhibitor)
        ->getJson('/api/exhibitor/events')
        ->assertSuccessful();

    expect(collect($response->json('data.0.brands'))->pluck('booth_number')->all())
        ->toBe(['A-1', 'A-2', 'A-10']);
});
