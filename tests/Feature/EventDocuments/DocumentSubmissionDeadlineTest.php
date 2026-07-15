<?php

use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\CustomField;
use App\Models\Event;
use App\Models\EventDocument;
use App\Models\EventDocumentSubmission;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['brands.read', 'brands.update'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web'])->syncPermissions(Permission::all());
    Role::firstOrCreate(['name' => 'exhibitor', 'guard_name' => 'web'])->syncPermissions(['brands.read', 'brands.update']);

    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create(['project_id' => $this->project->id]);

    $this->exhibitor = User::factory()->create(['email_verified_at' => now()]);
    $this->exhibitor->assignRole('exhibitor');

    $this->brand = Brand::factory()->create();
    $this->brand->users()->attach($this->exhibitor->id, ['role' => 'owner']);

    $this->brandEvent = BrandEvent::factory()->create([
        'brand_id' => $this->brand->id,
        'event_id' => $this->event->id,
        'booth_number' => 'A01',
    ]);

    // A document with one optional text field; submitting an answer succeeds
    // whenever the deadline allows it.
    $this->makeDocument = function ($deadline) {
        $doc = EventDocument::factory()->create([
            'event_id' => $this->event->id,
            'document_type' => 'custom',
            'is_required' => false,
            'blocks_next_step' => false,
            'booth_types' => null,
            'submission_deadline' => $deadline,
        ]);

        $this->field = CustomField::factory()->document($doc)->type(CustomField::TYPE_TEXT)->create();

        return $doc;
    };

    $this->submitUrlFor = fn (EventDocument $doc) => "/api/exhibitor/brands/{$this->brand->slug}/events/{$this->brandEvent->id}/documents/{$doc->ulid}";

    $this->answer = fn () => ['field_values' => [$this->field->ulid => 'Contractor XYZ']];
});

it('rejects a submission after the deadline for an exhibitor', function () {
    $doc = ($this->makeDocument)(now()->subDay());

    $this->actingAs($this->exhibitor)
        ->postJson(($this->submitUrlFor)($doc), ($this->answer)())
        ->assertStatus(422);

    expect(EventDocumentSubmission::query()->count())->toBe(0);
});

it('allows a submission when the document has no deadline', function () {
    $doc = ($this->makeDocument)(null);

    $this->actingAs($this->exhibitor)
        ->postJson(($this->submitUrlFor)($doc), ($this->answer)())
        ->assertSuccessful();
});

it('allows a submission before the deadline', function () {
    $doc = ($this->makeDocument)(now()->addDay());

    $this->actingAs($this->exhibitor)
        ->postJson(($this->submitUrlFor)($doc), ($this->answer)())
        ->assertSuccessful();
});

it('lets staff submit on the exhibitor behalf after the deadline', function () {
    $staff = User::factory()->create(['email_verified_at' => now()]);
    $staff->assignRole('master');
    $this->brand->users()->attach($staff->id, ['role' => 'owner']);

    $doc = ($this->makeDocument)(now()->subDay());

    $this->actingAs($staff)
        ->postJson(($this->submitUrlFor)($doc), ($this->answer)())
        ->assertSuccessful();
});

it('reports is_overdue through the documents endpoint', function () {
    ($this->makeDocument)(now()->subDay());

    $listing = $this->actingAs($this->exhibitor)
        ->getJson("/api/exhibitor/brands/{$this->brand->slug}/events/{$this->brandEvent->id}/documents")
        ->assertSuccessful();

    expect($listing->json('data.documents.0.document.is_overdue'))->toBeTrue();
});
