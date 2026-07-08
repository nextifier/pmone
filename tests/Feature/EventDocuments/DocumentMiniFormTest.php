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

    $this->document = EventDocument::factory()->create([
        'event_id' => $this->event->id,
        'document_type' => 'custom',
        'is_required' => true,
        'blocks_next_step' => false,
        'booth_types' => null,
    ]);

    $this->staffApiBase = "/api/projects/{$this->project->username}/events/{$this->event->slug}/documents";
    $this->fieldsApiBase = "{$this->staffApiBase}/{$this->document->ulid}/fields";
    $this->exhibitorApiBase = "/api/exhibitor/brands/{$this->brand->slug}/events/{$this->brandEvent->id}";

    $this->submitUrl = fn (EventDocument $document) => "{$this->exhibitorApiBase}/documents/{$document->ulid}";
});

// Admin sub-resource CRUD

it('staff can create a field on a document', function () {
    $response = $this->actingAs($this->staff)
        ->postJson($this->fieldsApiBase, [
            'label' => ['en' => 'Company registration number'],
            'type' => CustomField::TYPE_TEXT,
            'validation' => ['required' => true],
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.type', 'text')
        ->assertJsonPath('data.context', 'document')
        ->assertJsonPath('data.label', 'Company registration number')
        ->assertJsonPath('data.label_translations.en', 'Company registration number')
        ->assertJsonPath('data.validation.required', true);

    $this->assertDatabaseHas('custom_fields', [
        'fieldable_type' => EventDocument::class,
        'fieldable_id' => $this->document->id,
        'context' => CustomField::CONTEXT_DOCUMENT,
        'type' => 'text',
    ]);
});

it('coerces a plain-string label to the English translation', function () {
    $this->actingAs($this->staff)
        ->postJson($this->fieldsApiBase, [
            'label' => 'Booth contractor name',
            'type' => CustomField::TYPE_TEXT,
        ])
        ->assertCreated()
        ->assertJsonPath('data.label_translations.en', 'Booth contractor name');
});

it('rejects field types outside the document context whitelist', function () {
    $this->actingAs($this->staff)
        ->postJson($this->fieldsApiBase, [
            'label' => ['en' => 'Section break'],
            'type' => CustomField::TYPE_SECTION,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('type');
});

it('allows file fields in the document context', function () {
    $this->actingAs($this->staff)
        ->postJson($this->fieldsApiBase, [
            'label' => ['en' => 'Insurance certificate'],
            'type' => CustomField::TYPE_FILE,
            'validation' => ['required' => true, 'max_file_size' => 10240, 'allowed_file_types' => ['pdf']],
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'file');
});

it('staff can list document fields', function () {
    CustomField::factory()->document($this->document)->count(3)->create();

    $this->actingAs($this->staff)
        ->getJson($this->fieldsApiBase)
        ->assertSuccessful()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 3);
});

it('staff can update a document field', function () {
    $field = CustomField::factory()->document($this->document)->create();

    $this->actingAs($this->staff)
        ->putJson("{$this->fieldsApiBase}/{$field->ulid}", [
            'label' => ['en' => 'Updated label'],
            'validation' => ['required' => true],
            'is_active' => false,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.label_translations.en', 'Updated label')
        ->assertJsonPath('data.is_active', false);
});

it('staff can delete a document field', function () {
    $field = CustomField::factory()->document($this->document)->create();

    $this->actingAs($this->staff)
        ->deleteJson("{$this->fieldsApiBase}/{$field->ulid}")
        ->assertSuccessful();

    expect(CustomField::query()->find($field->id))->toBeNull();
});

it('returns 404 when the field belongs to another document', function () {
    $otherDocument = EventDocument::factory()->create([
        'event_id' => $this->event->id,
        'document_type' => 'custom',
    ]);
    $foreignField = CustomField::factory()->document($otherDocument)->create();

    $this->actingAs($this->staff)
        ->putJson("{$this->fieldsApiBase}/{$foreignField->ulid}", [
            'label' => ['en' => 'Hijacked'],
        ])
        ->assertNotFound();

    $this->actingAs($this->staff)
        ->deleteJson("{$this->fieldsApiBase}/{$foreignField->ulid}")
        ->assertNotFound();

    expect(CustomField::query()->find($foreignField->id))->not->toBeNull();
});

it('returns 404 when the document does not belong to the event in the url', function () {
    $otherEvent = Event::factory()->create(['project_id' => $this->project->id]);
    $otherDocument = EventDocument::factory()->create([
        'event_id' => $otherEvent->id,
        'document_type' => 'custom',
    ]);

    $this->actingAs($this->staff)
        ->getJson("{$this->staffApiBase}/{$otherDocument->ulid}/fields")
        ->assertNotFound();
});

it('staff can reorder document fields', function () {
    $first = CustomField::factory()->document($this->document)->create();
    $second = CustomField::factory()->document($this->document)->create();

    $this->actingAs($this->staff)
        ->putJson("{$this->fieldsApiBase}/reorder", [
            'orders' => [
                ['id' => $second->id, 'order' => 1],
                ['id' => $first->id, 'order' => 2],
            ],
        ])
        ->assertSuccessful();

    expect($second->fresh()->order_column)->toBe(1);
    expect($first->fresh()->order_column)->toBe(2);
});

it('rejects reorder payloads containing fields of another document', function () {
    $ownField = CustomField::factory()->document($this->document)->create();
    $otherDocument = EventDocument::factory()->create([
        'event_id' => $this->event->id,
        'document_type' => 'custom',
    ]);
    $foreignField = CustomField::factory()->document($otherDocument)->create();

    $this->actingAs($this->staff)
        ->putJson("{$this->fieldsApiBase}/reorder", [
            'orders' => [
                ['id' => $ownField->id, 'order' => 1],
                ['id' => $foreignField->id, 'order' => 2],
            ],
        ])
        ->assertUnprocessable();
});

it('embeds fields in the admin document show response', function () {
    $field = CustomField::factory()->document($this->document)->create();

    $this->actingAs($this->staff)
        ->getJson("{$this->staffApiBase}/{$this->document->ulid}")
        ->assertSuccessful()
        ->assertJsonPath('data.fields.0.ulid', $field->ulid);
});

it('staff can create a document without document_type (defaults to custom)', function () {
    $this->actingAs($this->staff)
        ->postJson($this->staffApiBase, [
            'title' => 'Stand Design Approval',
        ])
        ->assertCreated()
        ->assertJsonPath('data.document_type', 'custom');
});

// Exhibitor multi-field submission

it('validates required fields keyed by field_values.{ulid}', function () {
    $required = CustomField::factory()->document($this->document)->required()->create();

    $this->actingAs($this->exhibitor)
        ->postJson(($this->submitUrl)($this->document), ['field_values' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(["field_values.{$required->ulid}"]);
});

it('validates answers per field type', function () {
    $select = CustomField::factory()->document($this->document)
        ->type(CustomField::TYPE_SELECT)
        ->create();

    $this->actingAs($this->exhibitor)
        ->postJson(($this->submitUrl)($this->document), [
            'field_values' => [$select->ulid => 'not-an-option'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(["field_values.{$select->ulid}"]);
});

it('exhibitor can submit a multi-field document', function () {
    $text = CustomField::factory()->document($this->document)->required()->create();
    $select = CustomField::factory()->document($this->document)
        ->type(CustomField::TYPE_SELECT)
        ->create();

    $this->actingAs($this->exhibitor)
        ->postJson(($this->submitUrl)($this->document), [
            'field_values' => [
                $text->ulid => 'PT Contoh Jaya',
                $select->ulid => 'option-2',
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath("data.field_values.{$text->ulid}", 'PT Contoh Jaya')
        ->assertJsonPath("data.field_values.{$select->ulid}", 'option-2');

    $submission = EventDocumentSubmission::query()->firstOrFail();
    expect($submission->field_values[$text->ulid])->toBe('PT Contoh Jaya');
    expect($submission->booth_identifier)->toBe('A01');
    expect($submission->document_version)->toBe($this->document->fresh()->content_version);
});

it('mirrors a truthy agreement checkbox into agreed_at', function () {
    $agreement = CustomField::factory()->document($this->document)
        ->type(CustomField::TYPE_CHECKBOX)
        ->required()
        ->create(['system_key' => 'agreement']);

    $this->actingAs($this->exhibitor)
        ->postJson(($this->submitUrl)($this->document), [
            'field_values' => [$agreement->ulid => true],
        ])
        ->assertSuccessful();

    $submission = EventDocumentSubmission::query()->firstOrFail();
    expect($submission->agreed_at)->not->toBeNull();
    expect($submission->field_values[$agreement->ulid])->toBeTrue();
});

it('requires the agreement checkbox to be accepted', function () {
    $agreement = CustomField::factory()->document($this->document)
        ->type(CustomField::TYPE_CHECKBOX)
        ->required()
        ->create(['system_key' => 'agreement']);

    $this->actingAs($this->exhibitor)
        ->postJson(($this->submitUrl)($this->document), [
            'field_values' => [$agreement->ulid => false],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(["field_values.{$agreement->ulid}"]);
});

it('supports re-agreement after a content version bump', function () {
    $document = EventDocument::factory()->create([
        'event_id' => $this->event->id,
        'document_type' => 'checkbox_agreement',
        'blocks_next_step' => true,
        'is_required' => true,
        'content_version' => 1,
        'booth_types' => null,
    ]);
    $agreement = CustomField::factory()->document($document)
        ->type(CustomField::TYPE_CHECKBOX)
        ->required()
        ->create(['system_key' => 'agreement']);

    $this->actingAs($this->exhibitor)
        ->postJson(($this->submitUrl)($document), [
            'field_values' => [$agreement->ulid => true],
        ])
        ->assertSuccessful();

    $document->incrementContentVersion();

    $listing = $this->actingAs($this->exhibitor)
        ->getJson("{$this->exhibitorApiBase}/documents")
        ->assertSuccessful();

    $docData = collect($listing->json('data.documents'))->firstWhere('document.id', $document->id);
    expect($docData['submission']['needs_reagreement'])->toBeTrue();

    $this->actingAs($this->exhibitor)
        ->postJson(($this->submitUrl)($document), [
            'field_values' => [$agreement->ulid => true],
        ])
        ->assertSuccessful();

    $submission = EventDocumentSubmission::query()->firstOrFail();
    expect($submission->document_version)->toBe(2);
    expect($submission->needsReagreement())->toBeFalse();
    expect($submission->agreed_at)->not->toBeNull();
});

it('exposes fields and submission field_values in the exhibitor documents listing', function () {
    $text = CustomField::factory()->document($this->document)->create();

    $this->actingAs($this->exhibitor)
        ->postJson(($this->submitUrl)($this->document), [
            'field_values' => [$text->ulid => 'Listed answer'],
        ])
        ->assertSuccessful();

    $listing = $this->actingAs($this->exhibitor)
        ->getJson("{$this->exhibitorApiBase}/documents")
        ->assertSuccessful();

    $docData = collect($listing->json('data.documents'))->firstWhere('document.id', $this->document->id);
    expect($docData['document']['fields'][0]['ulid'])->toBe($text->ulid);
    expect($docData['submission']['field_values'][$text->ulid])->toBe('Listed answer');
});

// Legacy payload compatibility (kept for one release)

it('accepts the legacy text_value payload and maps it onto the legacy_text field', function () {
    $legacyText = CustomField::factory()->document($this->document)
        ->type(CustomField::TYPE_TEXTAREA)
        ->create(['system_key' => 'legacy_text']);

    $this->actingAs($this->exhibitor)
        ->postJson(($this->submitUrl)($this->document), [
            'text_value' => 'My legacy response',
        ])
        ->assertSuccessful();

    $submission = EventDocumentSubmission::query()->firstOrFail();
    expect($submission->field_values[$legacyText->ulid])->toBe('My legacy response');
    expect($submission->text_value)->toBe('My legacy response');
});

it('treats a legacy empty post to a checkbox_agreement document as agreement', function () {
    $document = EventDocument::factory()->eventRule()->create([
        'event_id' => $this->event->id,
        'booth_types' => null,
    ]);
    $agreement = CustomField::factory()->document($document)
        ->type(CustomField::TYPE_CHECKBOX)
        ->required()
        ->create(['system_key' => 'agreement']);

    $this->actingAs($this->exhibitor)
        ->postJson(($this->submitUrl)($document), [])
        ->assertSuccessful();

    $submission = EventDocumentSubmission::query()->firstOrFail();
    expect($submission->agreed_at)->not->toBeNull();
    expect($submission->field_values[$agreement->ulid])->toBeTrue();
});

it('accepts the legacy agreement boolean payload', function () {
    $agreement = CustomField::factory()->document($this->document)
        ->type(CustomField::TYPE_CHECKBOX)
        ->required()
        ->create(['system_key' => 'agreement']);

    $this->actingAs($this->exhibitor)
        ->postJson(($this->submitUrl)($this->document), ['agreement' => true])
        ->assertSuccessful();

    expect(EventDocumentSubmission::query()->firstOrFail()->agreed_at)->not->toBeNull();
});

// File fields

it('stores per-field files with the field_ulid custom property', function () {
    Storage::fake('public');
    Storage::fake('local');

    $file = CustomField::factory()->document($this->document)
        ->type(CustomField::TYPE_FILE)
        ->required()
        ->create();

    Storage::disk('local')->put('tmp/uploads/tmp-doc1/report.pdf', '%PDF-1.4 test content');
    Storage::disk('local')->put('tmp/uploads/tmp-doc1/metadata.json', json_encode(['original_name' => 'report.pdf']));

    $this->actingAs($this->exhibitor)
        ->postJson(($this->submitUrl)($this->document), [
            'files' => [$file->ulid => 'tmp-doc1'],
        ])
        ->assertSuccessful();

    $submission = EventDocumentSubmission::query()->firstOrFail();
    $media = $submission->getMedia('submission_file');

    expect($media)->toHaveCount(1);
    expect($media->first()->getCustomProperty('field_ulid'))->toBe($file->ulid);
    expect($submission->field_values[$file->ulid])->toBe([$media->first()->id]);
    expect(Storage::disk('local')->exists('tmp/uploads/tmp-doc1'))->toBeFalse();
});

it('rejects a submission missing a required file field', function () {
    $file = CustomField::factory()->document($this->document)
        ->type(CustomField::TYPE_FILE)
        ->required()
        ->create();

    $this->actingAs($this->exhibitor)
        ->postJson(($this->submitUrl)($this->document), ['field_values' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(["field_values.{$file->ulid}"]);
});

// Document-level gating

it('keeps booth type gating on multi-field submissions', function () {
    $document = EventDocument::factory()->create([
        'event_id' => $this->event->id,
        'document_type' => 'custom',
        'booth_types' => ['raw_space'],
    ]);
    CustomField::factory()->document($document)->create();

    // BrandEvent has no booth_type, so a raw_space-only document does not apply.
    $this->actingAs($this->exhibitor)
        ->postJson(($this->submitUrl)($document), ['field_values' => []])
        ->assertUnprocessable();
});

it('ignores answers for fields of other documents', function () {
    $ownField = CustomField::factory()->document($this->document)->create();
    $otherDocument = EventDocument::factory()->create([
        'event_id' => $this->event->id,
        'document_type' => 'custom',
    ]);
    $foreignField = CustomField::factory()->document($otherDocument)->create();

    $this->actingAs($this->exhibitor)
        ->postJson(($this->submitUrl)($this->document), [
            'field_values' => [
                $ownField->ulid => 'kept',
                $foreignField->ulid => 'dropped',
            ],
        ])
        ->assertSuccessful();

    $submission = EventDocumentSubmission::query()->firstOrFail();
    expect($submission->field_values)->toHaveKey($ownField->ulid);
    expect($submission->field_values)->not->toHaveKey($foreignField->ulid);
});
