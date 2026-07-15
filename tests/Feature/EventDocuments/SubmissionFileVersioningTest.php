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
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    foreach (['brands.read', 'brands.update', 'events.read'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $masterRole = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $masterRole->syncPermissions(Permission::all());
    $exhibitorRole = Role::firstOrCreate(['name' => 'exhibitor', 'guard_name' => 'web']);
    $exhibitorRole->syncPermissions(['brands.read', 'brands.update']);

    $this->staff = User::factory()->create(['email_verified_at' => now()]);
    $this->staff->assignRole('master');

    $this->exhibitor = User::factory()->create(['email_verified_at' => now()]);
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

    $this->document = EventDocument::factory()->create([
        'event_id' => $this->event->id,
        'document_type' => 'custom',
        'is_required' => true,
        'blocks_next_step' => false,
        'booth_types' => null,
    ]);

    $this->exhibitorApiBase = "/api/exhibitor/brands/{$this->brand->slug}/events/{$this->brandEvent->id}";
    $this->submitUrl = "{$this->exhibitorApiBase}/documents/{$this->document->ulid}";
});

/**
 * Prepare a tmp-upload folder with a fake file and return its id.
 */
function tmpDoc(string $name = 'report.pdf'): string
{
    $folder = 'tmp-'.uniqid();
    Storage::disk('local')->put("tmp/uploads/{$folder}/{$name}", '%PDF-1.4 test content');
    Storage::disk('local')->put("tmp/uploads/{$folder}/metadata.json", json_encode([
        'original_name' => $name,
        'mime_type' => 'application/pdf',
    ]));

    return $folder;
}

it('keeps the previous file as a superseded version on re-upload', function () {
    $file = CustomField::factory()->document($this->document)
        ->type(CustomField::TYPE_FILE)
        ->required()
        ->create();

    $this->actingAs($this->exhibitor)
        ->postJson($this->submitUrl, ['files' => [$file->ulid => tmpDoc('v1.pdf')]])
        ->assertSuccessful();

    $this->actingAs($this->exhibitor)
        ->postJson($this->submitUrl, ['files' => [$file->ulid => tmpDoc('v2.pdf')]])
        ->assertSuccessful();

    $submission = EventDocumentSubmission::query()->firstOrFail();
    $media = $submission->getMedia('submission_file');

    expect($media)->toHaveCount(2);

    $current = $submission->currentSubmissionFiles();
    expect($current)->toHaveCount(1);
    expect($current->first()->file_name)->toContain('v2');
    expect((int) $current->first()->getCustomProperty('version'))->toBe(2);

    $superseded = $media->filter(fn ($m) => $m->getCustomProperty('superseded_at') !== null);
    expect($superseded)->toHaveCount(1);
    expect($superseded->first()->file_name)->toContain('v1');
});

it('only exposes the current file to the exhibitor', function () {
    $file = CustomField::factory()->document($this->document)
        ->type(CustomField::TYPE_FILE)
        ->required()
        ->create();

    $this->actingAs($this->exhibitor)
        ->postJson($this->submitUrl, ['files' => [$file->ulid => tmpDoc('old.pdf')]])
        ->assertSuccessful();
    $this->actingAs($this->exhibitor)
        ->postJson($this->submitUrl, ['files' => [$file->ulid => tmpDoc('new.pdf')]])
        ->assertSuccessful();

    $listing = $this->actingAs($this->exhibitor)
        ->getJson("{$this->exhibitorApiBase}/documents")
        ->assertSuccessful();

    $docData = collect($listing->json('data.documents'))->firstWhere('document.id', $this->document->id);
    expect($docData['submission']['files'])->toHaveCount(1);
    expect($docData['submission']['files'][0]['name'])->toContain('new');
});

it('exposes the full version history to the exhibitor too', function () {
    $file = CustomField::factory()->document($this->document)
        ->type(CustomField::TYPE_FILE)
        ->required()
        ->create();

    $this->actingAs($this->exhibitor)
        ->postJson($this->submitUrl, ['files' => [$file->ulid => tmpDoc('v1.pdf')]])
        ->assertSuccessful();
    $this->actingAs($this->exhibitor)
        ->postJson($this->submitUrl, ['files' => [$file->ulid => tmpDoc('v2.pdf')]])
        ->assertSuccessful();

    $listing = $this->actingAs($this->exhibitor)
        ->getJson("{$this->exhibitorApiBase}/documents")
        ->assertSuccessful();

    $docData = collect($listing->json('data.documents'))->firstWhere('document.id', $this->document->id);
    $history = collect($docData['submission']['file_history'])->firstWhere('field_ulid', $file->ulid);

    expect($history['versions'])->toHaveCount(2);
    expect($history['versions'][0]['version'])->toBe(2);
    expect($history['versions'][0]['is_current'])->toBeTrue();
    expect($history['versions'][1]['version'])->toBe(1);
});

it('exposes the full version history to staff, newest first', function () {
    $file = CustomField::factory()->document($this->document)
        ->type(CustomField::TYPE_FILE)
        ->required()
        ->create();

    $this->actingAs($this->exhibitor)
        ->postJson($this->submitUrl, ['files' => [$file->ulid => tmpDoc('v1.pdf')]])
        ->assertSuccessful();
    $this->actingAs($this->exhibitor)
        ->postJson($this->submitUrl, ['files' => [$file->ulid => tmpDoc('v2.pdf')]])
        ->assertSuccessful();

    $response = $this->actingAs($this->staff)
        ->getJson("/api/projects/{$this->project->username}/events/{$this->event->slug}/brands/{$this->brand->slug}/document-submissions")
        ->assertSuccessful();

    $docData = collect($response->json('data'))->firstWhere('document.id', $this->document->id);
    $history = collect($docData['file_history'])->firstWhere('field_ulid', $file->ulid);

    expect($history['versions'])->toHaveCount(2);
    expect($history['versions'][0]['version'])->toBe(2);
    expect($history['versions'][0]['is_current'])->toBeTrue();
    expect($history['versions'][1]['version'])->toBe(1);
    expect($history['versions'][1]['is_current'])->toBeFalse();
});

it('retains only the five most recent versions', function () {
    $file = CustomField::factory()->document($this->document)
        ->type(CustomField::TYPE_FILE)
        ->required()
        ->create();

    for ($i = 1; $i <= 6; $i++) {
        $this->actingAs($this->exhibitor)
            ->postJson($this->submitUrl, ['files' => [$file->ulid => tmpDoc("v{$i}.pdf")]])
            ->assertSuccessful();
    }

    $submission = EventDocumentSubmission::query()->firstOrFail();
    $media = $submission->getMedia('submission_file');

    expect($media)->toHaveCount(5);
    // The oldest (v1) was pruned; versions 2..6 remain.
    $versions = $media->map(fn ($m) => (int) $m->getCustomProperty('version'))->sort()->values()->all();
    expect($versions)->toBe([2, 3, 4, 5, 6]);
});

it('does not version multiple-file fields', function () {
    $file = CustomField::factory()->document($this->document)
        ->type(CustomField::TYPE_FILE)
        ->required()
        ->create(['settings' => ['multiple' => true]]);

    $this->actingAs($this->exhibitor)
        ->postJson($this->submitUrl, ['files' => [$file->ulid => tmpDoc('a.pdf')]])
        ->assertSuccessful();
    $this->actingAs($this->exhibitor)
        ->postJson($this->submitUrl, ['files' => [$file->ulid => tmpDoc('b.pdf')]])
        ->assertSuccessful();

    $submission = EventDocumentSubmission::query()->firstOrFail();

    // Both files remain current; nothing is superseded.
    expect($submission->currentSubmissionFiles())->toHaveCount(2);
    expect($submission->getMedia('submission_file')
        ->filter(fn ($m) => $m->getCustomProperty('superseded_at') !== null))->toHaveCount(0);
});

it('summarizes the current file version only', function () {
    $file = CustomField::factory()->document($this->document)
        ->type(CustomField::TYPE_FILE)
        ->create();

    $this->actingAs($this->exhibitor)
        ->postJson($this->submitUrl, ['files' => [$file->ulid => tmpDoc('old.pdf')]])
        ->assertSuccessful();
    $this->actingAs($this->exhibitor)
        ->postJson($this->submitUrl, ['files' => [$file->ulid => tmpDoc('new.pdf')]])
        ->assertSuccessful();

    $submission = EventDocumentSubmission::query()->firstOrFail();
    $summary = $this->document->fresh()->submissionSummary($submission);

    expect($summary)->toContain('new');
    expect($summary)->not->toContain('old');
});
