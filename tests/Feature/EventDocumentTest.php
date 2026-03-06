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
    $permissions = [
        'event_documents.create', 'event_documents.read',
        'event_documents.update', 'event_documents.delete',
        'events.read', 'brands.read', 'brands.update',
    ];

    foreach ($permissions as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    $masterRole = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $masterRole->syncPermissions(Permission::all());

    $exhibitorRole = Role::firstOrCreate(['name' => 'exhibitor', 'guard_name' => 'web']);
    $exhibitorRole->syncPermissions(['brands.read', 'brands.update']);

    $this->staff = User::factory()->create();
    $this->staff->assignRole('master');

    $this->exhibitor = User::factory()->create();
    $this->exhibitor->assignRole('exhibitor');

    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create(['project_id' => $this->project->id]);

    $this->brand = Brand::factory()->create();
    $this->brand->users()->attach($this->exhibitor->id, ['role' => 'owner']);
    $this->brandEvent = BrandEvent::factory()->create([
        'brand_id' => $this->brand->id,
        'event_id' => $this->event->id,
        'booth_number' => 'A01',
    ]);

    $this->staffApiBase = "/api/projects/{$this->project->username}/events/{$this->event->slug}/documents";
    $this->exhibitorApiBase = "/api/exhibitor/brands/{$this->brand->slug}/events/{$this->brandEvent->id}";
});

// Staff CRUD Tests

it('staff can list event documents', function () {
    EventDocument::factory()->count(3)->create(['event_id' => $this->event->id]);

    $response = $this->actingAs($this->staff)
        ->getJson($this->staffApiBase);

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

it('staff can create an event document', function () {
    $response = $this->actingAs($this->staff)
        ->postJson($this->staffApiBase, [
            'title' => 'Event Rules & Regulations',
            'document_type' => 'checkbox_agreement',
            'is_required' => true,
            'blocks_next_step' => true,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Event Rules & Regulations')
        ->assertJsonPath('data.document_type', 'checkbox_agreement')
        ->assertJsonPath('data.is_required', true)
        ->assertJsonPath('data.blocks_next_step', true);
});

it('staff can create document with booth type filter', function () {
    $response = $this->actingAs($this->staff)
        ->postJson($this->staffApiBase, [
            'title' => 'Raw Space Guidelines',
            'document_type' => 'file_upload',
            'booth_types' => ['raw_space'],
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.booth_types', ['raw_space']);
});

it('staff can update document and increment version', function () {
    $doc = EventDocument::factory()->create([
        'event_id' => $this->event->id,
        'content_version' => 1,
    ]);

    $response = $this->actingAs($this->staff)
        ->putJson("{$this->staffApiBase}/{$doc->ulid}", [
            'title' => 'Updated Title',
            'increment_version' => true,
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.title', 'Updated Title')
        ->assertJsonPath('data.content_version', 2);
});

it('staff can delete a document', function () {
    $doc = EventDocument::factory()->create(['event_id' => $this->event->id]);

    $response = $this->actingAs($this->staff)
        ->deleteJson("{$this->staffApiBase}/{$doc->ulid}");

    $response->assertSuccessful();
    $this->assertDatabaseMissing('event_documents', ['id' => $doc->id]);
});

it('staff can reorder documents', function () {
    $doc1 = EventDocument::factory()->create(['event_id' => $this->event->id]);
    $doc2 = EventDocument::factory()->create(['event_id' => $this->event->id]);

    $response = $this->actingAs($this->staff)
        ->postJson("{$this->staffApiBase}/reorder", [
            'orders' => [
                ['id' => $doc2->id, 'order' => 1],
                ['id' => $doc1->id, 'order' => 2],
            ],
        ]);

    $response->assertSuccessful();
    expect($doc2->fresh()->order_column)->toBe(1);
    expect($doc1->fresh()->order_column)->toBe(2);
})->skip(env('DB_CONNECTION', 'sqlite') === 'sqlite', 'Reorder uses PostgreSQL-specific SQL');

it('validates document type on create', function () {
    $response = $this->actingAs($this->staff)
        ->postJson($this->staffApiBase, [
            'title' => 'Test Doc',
            'document_type' => 'invalid_type',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('document_type');
});

// Exhibitor Document Submission Tests

it('exhibitor can view event documents', function () {
    EventDocument::factory()->count(2)->create(['event_id' => $this->event->id]);

    $response = $this->actingAs($this->exhibitor)
        ->getJson("{$this->exhibitorApiBase}/documents");

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data.documents');
});

it('exhibitor can agree to checkbox document', function () {
    $doc = EventDocument::factory()->eventRule()->create([
        'event_id' => $this->event->id,
    ]);

    $response = $this->actingAs($this->exhibitor)
        ->postJson("{$this->exhibitorApiBase}/documents/{$doc->ulid}", []);

    $response->assertSuccessful();

    $this->assertDatabaseHas('event_document_submissions', [
        'event_document_id' => $doc->id,
        'booth_identifier' => 'A01',
        'event_id' => $this->event->id,
        'submitted_by' => $this->exhibitor->id,
    ]);
});

it('exhibitor can submit text input document', function () {
    $doc = EventDocument::factory()->textInput()->create([
        'event_id' => $this->event->id,
    ]);

    $response = $this->actingAs($this->exhibitor)
        ->postJson("{$this->exhibitorApiBase}/documents/{$doc->ulid}", [
            'text_value' => 'My response text',
        ]);

    $response->assertSuccessful();

    $this->assertDatabaseHas('event_document_submissions', [
        'event_document_id' => $doc->id,
        'text_value' => 'My response text',
    ]);
});

it('exhibitor submission detects reagreement needed', function () {
    $doc = EventDocument::factory()->eventRule()->create([
        'event_id' => $this->event->id,
        'content_version' => 1,
    ]);

    // Initial agreement
    $this->actingAs($this->exhibitor)
        ->postJson("{$this->exhibitorApiBase}/documents/{$doc->ulid}", []);

    // Increment version
    $doc->incrementContentVersion();

    // Fetch documents again
    $response = $this->actingAs($this->exhibitor)
        ->getJson("{$this->exhibitorApiBase}/documents");

    $response->assertSuccessful();
    $docData = collect($response->json('data.documents'))->firstWhere('document.id', $doc->id);
    expect($docData['submission']['needs_reagreement'])->toBeTrue();
});

it('filters documents by booth type', function () {
    EventDocument::factory()->create([
        'event_id' => $this->event->id,
        'booth_types' => null,
    ]);
    EventDocument::factory()->create([
        'event_id' => $this->event->id,
        'booth_types' => ['raw_space'],
    ]);

    // BrandEvent has no booth_type, so null booth_types should pass
    $response = $this->actingAs($this->exhibitor)
        ->getJson("{$this->exhibitorApiBase}/documents");

    $response->assertSuccessful();
    // null booth_types = applies to all, ['raw_space'] does NOT apply when booth_type is null
    $response->assertJsonCount(1, 'data.documents');
});

// Order Period Tests

it('returns order period info', function () {
    $this->event->update([
        'normal_order_opens_at' => now()->subDay(),
        'normal_order_closes_at' => now()->addDays(5),
        'onsite_order_opens_at' => now()->addDays(6),
        'onsite_order_closes_at' => now()->addDays(10),
        'onsite_penalty_rate' => 50,
    ]);

    $response = $this->actingAs($this->exhibitor)
        ->getJson("{$this->exhibitorApiBase}/order-period");

    $response->assertSuccessful()
        ->assertJsonPath('data.current_period', 'normal_order')
        ->assertJsonPath('data.can_order', true)
        ->assertJsonPath('data.penalty_rate', 0);
});

it('returns onsite period with penalty', function () {
    $this->event->update([
        'normal_order_opens_at' => now()->subDays(10),
        'normal_order_closes_at' => now()->subDay(),
        'onsite_order_opens_at' => now()->subHour(),
        'onsite_order_closes_at' => now()->addDays(3),
        'onsite_penalty_rate' => 50,
    ]);

    $response = $this->actingAs($this->exhibitor)
        ->getJson("{$this->exhibitorApiBase}/order-period");

    $response->assertSuccessful()
        ->assertJsonPath('data.current_period', 'onsite_order')
        ->assertJsonPath('data.can_order', true)
        ->assertJsonPath('data.penalty_rate', 50);
});
