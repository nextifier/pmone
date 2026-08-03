<?php

use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\CustomField;
use App\Models\Event;
use App\Models\EventDocument;
use App\Models\Order;
use App\Models\Project;
use App\Models\PromotionPost;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.sheets.api_token' => 'test-sheets-token']);
    $this->token = 'test-sheets-token';
});

/**
 * @param  array<int, string>  $headings
 */
function cell(array $headings, array $row, string $heading): mixed
{
    $index = array_search($heading, $headings, true);

    expect($index)->not->toBeFalse("heading [{$heading}] is missing");

    return $row[$index];
}

it('rejects scoped requests without a valid token', function () {
    $event = Event::factory()->create();

    $this->getJson('/api/sheets/events?token=wrong')->assertStatus(401);
    $this->getJson("/api/sheets/events/{$event->id}/orders?token=wrong")->assertStatus(401);
    $this->getJson("/api/sheets/events/{$event->id}/brands?token=wrong")->assertStatus(401);
    $this->getJson("/api/sheets/events/{$event->id}/brand-events?token=wrong")->assertStatus(401);
    $this->getJson("/api/sheets/events/{$event->id}/operational-documents?token=wrong")->assertStatus(401);
});

it('rejects every feed when no api token is configured', function () {
    config(['services.sheets.api_token' => null]);

    $this->getJson('/api/sheets/orders')->assertStatus(401);
    $this->getJson('/api/sheets/brands')->assertStatus(401);
});

it('404s for an unknown, non-numeric or soft-deleted event', function () {
    $event = Event::factory()->create();
    $event->delete();

    $this->getJson("/api/sheets/events/999999/orders?token={$this->token}")->assertStatus(404);
    $this->getJson("/api/sheets/events/some-slug/orders?token={$this->token}")->assertStatus(404);
    $this->getJson("/api/sheets/events/{$event->id}/orders?token={$this->token}")->assertStatus(404);
});

it('limits the orders sheet to the scoped event', function () {
    $eventA = Event::factory()->create(['title' => 'Event A']);
    $eventB = Event::factory()->create(['title' => 'Event B']);

    $orderA = Order::factory()->create([
        'brand_event_id' => BrandEvent::factory()->create(['event_id' => $eventA->id])->id,
        'submitted_at' => now(),
    ]);
    $orderB = Order::factory()->create([
        'brand_event_id' => BrandEvent::factory()->create(['event_id' => $eventB->id])->id,
        'submitted_at' => now(),
    ]);

    $response = $this->getJson("/api/sheets/events/{$eventA->id}/orders?token={$this->token}")->assertSuccessful();

    $headings = $response->json('headings');
    $orderNumberCol = array_search('Order Number', $headings, true);
    $orderNumbers = collect($response->json('rows'))->pluck($orderNumberCol);

    expect($orderNumbers)->toContain($orderA->order_number)
        ->not->toContain($orderB->order_number);
    expect($response->json('title'))->toBe('Orders - Event A');

    // The global feed is untouched.
    $global = $this->getJson("/api/sheets/orders?token={$this->token}")->assertSuccessful();
    expect(collect($global->json('rows'))->pluck($orderNumberCol))
        ->toContain($orderA->order_number)
        ->toContain($orderB->order_number);
});

it('limits the brands sheet to brands taking part in the scoped event', function () {
    $eventA = Event::factory()->create();
    $eventB = Event::factory()->create();

    $exhibiting = Brand::factory()->create(['name' => 'Exhibiting Brand']);
    $absent = Brand::factory()->create(['name' => 'Absent Brand']);

    BrandEvent::factory()->create(['brand_id' => $exhibiting->id, 'event_id' => $eventA->id]);
    BrandEvent::factory()->create(['brand_id' => $absent->id, 'event_id' => $eventB->id]);

    $response = $this->getJson("/api/sheets/events/{$eventA->id}/brands?token={$this->token}")->assertSuccessful();

    $nameCol = array_search('Name', $response->json('headings'), true);
    $names = collect($response->json('rows'))->pluck($nameCol);

    expect($names)->toContain('Exhibiting Brand')->not->toContain('Absent Brand');
});

it('scopes the brand aggregate columns to the event', function () {
    $sales = User::factory()->create(['name' => 'Sales A']);
    $otherSales = User::factory()->create(['name' => 'Sales B']);

    $eventA = Event::factory()->create(['title' => 'Event A']);
    $eventB = Event::factory()->create(['title' => 'Event B']);

    $brand = Brand::factory()->create(['name' => 'Dual Brand']);

    $boothA = BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $eventA->id,
        'booth_number' => 'A-01',
        'sales_id' => $sales->id,
    ]);
    $boothB = BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $eventB->id,
        'booth_number' => 'B-99',
        'sales_id' => $otherSales->id,
    ]);

    Visit::factory()->count(3)->create(['visitable_type' => BrandEvent::class, 'visitable_id' => $boothA->id]);
    Visit::factory()->count(5)->create(['visitable_type' => BrandEvent::class, 'visitable_id' => $boothB->id]);
    PromotionPost::factory()->count(2)->create(['brand_event_id' => $boothA->id]);
    PromotionPost::factory()->count(4)->create(['brand_event_id' => $boothB->id]);

    $scoped = $this->getJson("/api/sheets/events/{$eventA->id}/brands?token={$this->token}")->assertSuccessful();
    $headings = $scoped->json('headings');
    $row = $scoped->json('rows.0');

    expect(cell($headings, $row, 'Events Count'))->toBe(1);
    expect(cell($headings, $row, 'Events List'))->toBe('Event A');
    expect(cell($headings, $row, 'Booth Numbers'))->toBe('A-01');
    expect(cell($headings, $row, 'Sales PICs'))->toBe('Sales A');
    expect(cell($headings, $row, 'Total Visits'))->toBe(3);
    expect(cell($headings, $row, 'Total Promotion Posts'))->toBe(2);

    // Same brand on the global feed still totals both events.
    $global = $this->getJson("/api/sheets/brands?token={$this->token}")->assertSuccessful();
    $globalHeadings = $global->json('headings');
    $globalRow = $global->json('rows.0');

    expect(cell($globalHeadings, $globalRow, 'Events Count'))->toBe(2);
    expect(cell($globalHeadings, $globalRow, 'Total Visits'))->toBe(8);
    expect(cell($globalHeadings, $globalRow, 'Total Promotion Posts'))->toBe(6);
});

it('only includes brand custom fields belonging to the event project', function () {
    $projectA = Project::factory()->create();
    $projectB = Project::factory()->create();

    CustomField::factory()->brand($projectA)->type(CustomField::TYPE_TEXT)->create([
        'label' => ['en' => 'Buyer Target'],
        'key' => 'buyer_target',
    ]);
    CustomField::factory()->brand($projectB)->type(CustomField::TYPE_TEXT)->create([
        'label' => ['en' => 'Shipping Agent'],
        'key' => 'shipping_agent',
    ]);

    $eventA = Event::factory()->create(['project_id' => $projectA->id]);
    BrandEvent::factory()->create([
        'brand_id' => Brand::factory()->create(['custom_fields' => ['buyer_target' => 'Investors']])->id,
        'event_id' => $eventA->id,
    ]);

    $headings = $this->getJson("/api/sheets/events/{$eventA->id}/brands?token={$this->token}")
        ->assertSuccessful()
        ->json('headings');

    expect($headings)->toContain('Buyer Target')->not->toContain('Shipping Agent');

    // The global feed still carries both projects' fields.
    $globalHeadings = $this->getJson("/api/sheets/brands?token={$this->token}")
        ->assertSuccessful()
        ->json('headings');

    expect($globalHeadings)->toContain('Buyer Target')->toContain('Shipping Agent');
});

it('limits the brand events sheet to the scoped event', function () {
    $eventA = Event::factory()->create(['title' => 'Event A']);
    $eventB = Event::factory()->create(['title' => 'Event B']);

    BrandEvent::factory()->create(['event_id' => $eventA->id, 'booth_number' => 'A-01']);
    BrandEvent::factory()->create(['event_id' => $eventB->id, 'booth_number' => 'B-99']);

    $response = $this->getJson("/api/sheets/events/{$eventA->id}/brand-events?token={$this->token}")->assertSuccessful();

    $headings = $response->json('headings');
    $boothCol = array_search('Booth Number', $headings, true);
    $eventTitleCol = array_search('Event Title', $headings, true);

    expect(collect($response->json('rows'))->pluck($boothCol))->toContain('A-01')->not->toContain('B-99');
    expect(collect($response->json('rows'))->pluck($eventTitleCol)->unique()->all())->toBe(['Event A']);
});

it('limits the operational documents sheet to the scoped event', function () {
    $eventA = Event::factory()->create();
    $eventB = Event::factory()->create();

    BrandEvent::factory()->create(['event_id' => $eventA->id, 'booth_number' => 'A-01']);
    BrandEvent::factory()->create(['event_id' => $eventB->id, 'booth_number' => 'B-99']);

    EventDocument::factory()->create([
        'event_id' => $eventA->id,
        'title' => 'Insurance A',
        'document_type' => 'file_upload',
        'booth_types' => null,
    ]);
    EventDocument::factory()->create([
        'event_id' => $eventB->id,
        'title' => 'Insurance B',
        'document_type' => 'file_upload',
        'booth_types' => null,
    ]);

    $response = $this->getJson("/api/sheets/events/{$eventA->id}/operational-documents?token={$this->token}")->assertSuccessful();

    $titleCol = array_search('Document Title', $response->json('headings'), true);
    $titles = collect($response->json('rows'))->pluck($titleCol);

    expect($titles)->toContain('Insurance A')->not->toContain('Insurance B');
});

it('keeps the operational documents columns for an event with no booths yet', function () {
    $event = Event::factory()->create();

    $document = EventDocument::factory()->create([
        'event_id' => $event->id,
        'title' => 'Insurance',
        'document_type' => 'file_upload',
        'booth_types' => null,
    ]);
    CustomField::factory()->document($document)->type(CustomField::TYPE_TEXT)->create([
        'label' => ['en' => 'Policy Number'],
        'key' => 'policy_number',
    ]);

    $response = $this->getJson("/api/sheets/events/{$event->id}/operational-documents?token={$this->token}")->assertSuccessful();

    expect($response->json('rows'))->toBe([]);
    expect($response->json('headings'))->toContain('Policy Number');
});

it('lists events with their counts and ready-to-paste feed urls', function () {
    $project = Project::factory()->create(['name' => 'Global AI', 'username' => 'gae']);
    $event = Event::factory()->create(['project_id' => $project->id, 'title' => 'Global AI Expo 2026']);
    $trashed = Event::factory()->create(['title' => 'Archived Expo']);
    $trashed->delete();

    $brandEvent = BrandEvent::factory()->create(['event_id' => $event->id]);
    Order::factory()->count(2)->create(['brand_event_id' => $brandEvent->id]);

    $response = $this->getJson("/api/sheets/events?token={$this->token}")->assertSuccessful();

    $headings = $response->json('headings');
    $rows = collect($response->json('rows'));
    $titleCol = array_search('Event Title', $headings, true);

    expect($rows->pluck($titleCol))->toContain('Global AI Expo 2026')->not->toContain('Archived Expo');

    $row = $rows->firstWhere($titleCol, 'Global AI Expo 2026');

    expect(cell($headings, $row, 'ID'))->toBe($event->id);
    expect(cell($headings, $row, 'Project Username'))->toBe('gae');
    expect(cell($headings, $row, 'Brand Events Count'))->toBe(1);
    expect(cell($headings, $row, 'Orders Count'))->toBe(2);
    expect(cell($headings, $row, 'Orders Sheet URL'))->toContain("/api/sheets/events/{$event->id}/orders");
    expect(cell($headings, $row, 'Brands Sheet URL'))->toContain("/api/sheets/events/{$event->id}/brands");
    expect($response->json('title'))->toBe('Events');
});
