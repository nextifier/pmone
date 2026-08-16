<?php

use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\EventDocument;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach ([
        'brands.read', 'brands.update',
        'event_documents.read', 'event_documents.create', 'event_documents.update',
    ] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web'])->syncPermissions(Permission::all());
    Role::firstOrCreate(['name' => 'exhibitor', 'guard_name' => 'web'])
        ->syncPermissions(['brands.read', 'brands.update']);

    $this->staff = User::factory()->create(['email_verified_at' => now()]);
    $this->staff->assignRole('master');

    $this->exhibitor = User::factory()->create(['email_verified_at' => now()]);
    $this->exhibitor->assignRole('exhibitor');

    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create([
        'project_id' => $this->project->id,
        'is_active' => true,
    ]);

    $this->brand = Brand::factory()->create();
    $this->brand->users()->attach($this->exhibitor->id, ['role' => 'owner']);
    $this->brandEvent = BrandEvent::factory()->create([
        'brand_id' => $this->brand->id,
        'event_id' => $this->event->id,
        'booth_number' => 'A01',
    ]);

    $this->visible = EventDocument::factory()->create([
        'event_id' => $this->event->id,
        'title' => 'Visible Document',
        'document_type' => 'custom',
        'booth_types' => null,
    ]);
    $this->hidden = EventDocument::factory()->create([
        'event_id' => $this->event->id,
        'title' => 'Hidden Document',
        'document_type' => 'custom',
        'booth_types' => null,
        'is_active' => false,
    ]);

    $this->adminBase = "/api/projects/{$this->project->username}/events/{$this->event->slug}/documents";
    $this->exhibitorBase = "/api/exhibitor/brands/{$this->brand->slug}/events/{$this->brandEvent->id}";
});

it('defaults a new document to visible', function () {
    $document = EventDocument::factory()->create(['event_id' => $this->event->id]);

    expect($document->fresh()->is_active)->toBeTrue();
});

it('lets staff hide a document without resending the rest of it', function () {
    $this->actingAs($this->staff)
        ->putJson("{$this->adminBase}/{$this->visible->ulid}", ['is_active' => false])
        ->assertSuccessful()
        ->assertJsonPath('data.is_active', false);

    expect($this->visible->fresh())
        ->is_active->toBeFalse()
        ->title->toBe('Visible Document');
});

it('hides an inactive document from the exhibitor document list', function () {
    $titles = collect(
        $this->actingAs($this->exhibitor)
            ->getJson("{$this->exhibitorBase}/documents")
            ->assertSuccessful()
            ->json('data.documents')
    )->pluck('document.title');

    expect($titles)->toContain('Visible Document')
        ->and($titles)->not->toContain('Hidden Document');
});

it('leaves an inactive document out of the dashboard counters', function () {
    $brandEvent = collect(
        $this->actingAs($this->exhibitor)
            ->getJson('/api/exhibitor/dashboard')
            ->assertSuccessful()
            ->json('data.brand_events')
    )->firstWhere('brand_event_id', $this->brandEvent->id);

    expect($brandEvent['documents_total'])->toBe(1)
        ->and(collect($brandEvent['documents'])->pluck('document.title'))
        ->not->toContain('Hidden Document');
});

it('does not let a hidden blocking rule lock the dashboard', function () {
    // An event rule that blocks the next step, then hidden: the exhibitor must
    // not be held at a gate they cannot see.
    EventDocument::factory()->create([
        'event_id' => $this->event->id,
        'title' => 'Hidden Rule',
        'document_type' => 'checkbox_agreement',
        'blocks_next_step' => true,
        'is_required' => true,
        'booth_types' => null,
        'is_active' => false,
    ]);

    $brandEvent = collect(
        $this->actingAs($this->exhibitor)
            ->getJson('/api/exhibitor/dashboard')
            ->assertSuccessful()
            ->json('data.brand_events')
    )->firstWhere('brand_event_id', $this->brandEvent->id);

    expect($brandEvent['event_rules'])->toBeEmpty()
        ->and($brandEvent['event_rules_agreed'])->toBeTrue();
});

it('rejects a submission aimed at a hidden document', function () {
    $this->actingAs($this->exhibitor)
        ->postJson("{$this->exhibitorBase}/documents/{$this->hidden->ulid}", ['text_value' => 'sneaky'])
        ->assertNotFound();
});

it('still shows inactive documents to staff', function () {
    $titles = collect(
        $this->actingAs($this->staff)
            ->getJson($this->adminBase)
            ->assertSuccessful()
            ->json('data')
    )->pluck('title');

    expect($titles)->toContain('Visible Document', 'Hidden Document');
});

it('still lists inactive documents in the sheets feed', function () {
    config(['services.sheets.api_token' => 'test-sheets-token']);

    $response = $this->getJson('/api/sheets/operational-documents?token=test-sheets-token')
        ->assertSuccessful();

    $titleCol = array_search('Document Title', $response->json('headings'), true);
    $titles = collect($response->json('rows'))->pluck($titleCol);

    expect($titles)->toContain('Visible Document', 'Hidden Document');
});
