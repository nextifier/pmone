<?php

use App\Models\CustomField;
use App\Models\Event;
use App\Models\EventDocument;
use App\Models\EventDocumentSubmission;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create(['project_id' => $this->project->id]);

    $this->makeDocument = fn (array $attributes = []) => EventDocument::factory()->create([
        'event_id' => $this->event->id,
        'document_type' => 'custom',
        'blocks_next_step' => false,
        ...$attributes,
    ])->refresh();

    $this->makeSubmission = fn (EventDocument $document, array $attributes = []) => EventDocumentSubmission::factory()->create([
        'event_document_id' => $document->id,
        'event_id' => $this->event->id,
        'booth_identifier' => 'A01',
        'document_version' => $document->content_version,
        'agreed_at' => null,
        'text_value' => null,
        'field_values' => null,
        ...$attributes,
    ]);
});

it('treats a document with no submission as incomplete', function () {
    $document = ($this->makeDocument)();

    expect($document->isSubmissionComplete(null))->toBeFalse()
        ->and($document->submissionSummary(null))->toBe('-');
});

it('marks a mini-form document complete once it has field values', function () {
    $document = ($this->makeDocument)();
    $field = CustomField::factory()->document($document)->create([
        'type' => CustomField::TYPE_TEXT,
        'label' => ['en' => 'Contractor'],
    ]);

    $submission = ($this->makeSubmission)($document, [
        'field_values' => [$field->ulid => 'PT Maju Jaya'],
    ]);

    expect($document->fresh()->isSubmissionComplete($submission))->toBeTrue();
});

it('renders a single mini-form answer without a label prefix', function () {
    $document = ($this->makeDocument)();
    $field = CustomField::factory()->document($document)->create([
        'type' => CustomField::TYPE_TEXT,
        'label' => ['en' => 'Contractor'],
    ]);

    $submission = ($this->makeSubmission)($document, [
        'field_values' => [$field->ulid => 'PT Maju Jaya'],
    ]);

    expect($document->fresh()->submissionSummary($submission))->toBe('PT Maju Jaya');
});

it('labels each answer when a mini-form has several fields', function () {
    $document = ($this->makeDocument)();
    $name = CustomField::factory()->document($document)->create([
        'type' => CustomField::TYPE_TEXT,
        'label' => ['en' => 'Contractor'],
        'order_column' => 1,
    ]);
    $phone = CustomField::factory()->document($document)->create([
        'type' => CustomField::TYPE_PHONE,
        'label' => ['en' => 'Phone'],
        'order_column' => 2,
    ]);

    $submission = ($this->makeSubmission)($document, [
        'field_values' => [$name->ulid => 'PT Maju Jaya', $phone->ulid => '08123'],
    ]);

    expect($document->fresh()->submissionSummary($submission))
        ->toBe('Contractor: PT Maju Jaya | Phone: 08123');
});

it('requires the agreement checkbox before an event rule counts as complete', function () {
    $document = ($this->makeDocument)(['blocks_next_step' => true]);
    $checkbox = CustomField::factory()->document($document)->create([
        'type' => CustomField::TYPE_CHECKBOX,
        'label' => ['en' => 'I agree'],
        'validation' => ['required' => true],
    ]);

    expect($document->fresh()->isEventRule())->toBeTrue();

    $unchecked = ($this->makeSubmission)($document, [
        'field_values' => [$checkbox->ulid => false],
    ]);

    expect($document->fresh()->isSubmissionComplete($unchecked))->toBeFalse()
        ->and($document->fresh()->submissionSummary($unchecked))->toBe('-');

    $unchecked->update(['agreed_at' => now(), 'field_values' => [$checkbox->ulid => true]]);

    expect($document->fresh()->isSubmissionComplete($unchecked->fresh()))->toBeTrue()
        ->and($document->fresh()->submissionSummary($unchecked->fresh()))->toStartWith('Agreed (');
});

it('skips fields the exhibitor left empty', function () {
    $document = ($this->makeDocument)();
    $name = CustomField::factory()->document($document)->create([
        'type' => CustomField::TYPE_TEXT,
        'label' => ['en' => 'Contractor'],
        'order_column' => 1,
    ]);
    CustomField::factory()->document($document)->create([
        'type' => CustomField::TYPE_TEXT,
        'label' => ['en' => 'Notes'],
        'order_column' => 2,
    ]);

    $submission = ($this->makeSubmission)($document, [
        'field_values' => [$name->ulid => 'PT Maju Jaya'],
    ]);

    expect($document->fresh()->submissionSummary($submission))->toBe('Contractor: PT Maju Jaya');
});

it('keeps the legacy document_type fallback for documents without a mini-form', function () {
    $checkbox = ($this->makeDocument)([
        'document_type' => 'checkbox_agreement',
        'blocks_next_step' => true,
    ]);
    $text = ($this->makeDocument)(['document_type' => 'text_input']);

    $agreed = ($this->makeSubmission)($checkbox, ['agreed_at' => now()]);
    $typed = ($this->makeSubmission)($text, ['text_value' => 'Booth 12']);

    expect($checkbox->isSubmissionComplete($agreed))->toBeTrue()
        ->and($checkbox->submissionSummary($agreed))->toStartWith('Agreed (')
        ->and($text->isSubmissionComplete($typed))->toBeTrue()
        ->and($text->submissionSummary($typed))->toBe('Booth 12');
});
