<?php

use App\Models\CustomField;
use App\Models\Event;
use App\Models\EventDocument;
use App\Models\Form;
use App\Models\Project;
use App\Models\User;
use App\Services\CustomFieldMigrator;
use App\Support\FormFieldTypes;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('creates the unified custom-field tables and columns', function () {
    expect(Schema::hasTable('custom_fields'))->toBeTrue()
        ->and(Schema::hasTable('custom_field_values'))->toBeTrue()
        ->and(Schema::hasColumn('event_document_submissions', 'field_values'))->toBeTrue();
});

it('backfills form fields preserving ulids verbatim', function () {
    $form = Form::factory()->create();
    $ulid = (string) Str::ulid();

    DB::table('form_fields')->insert([
        'ulid' => $ulid,
        'form_id' => $form->id,
        'type' => 'select',
        'label' => 'Ticket type',
        'placeholder' => 'Pick one',
        'help_text' => null,
        'options' => json_encode([['value' => 'visitor', 'label' => 'Visitor (Free)']]),
        'validation' => json_encode(['required' => true]),
        'settings' => json_encode(['param_key' => 'ticket']),
        'order_column' => 3,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(CustomFieldMigrator::class)->run();

    $field = CustomField::query()->where('ulid', $ulid)->first();

    expect($field)->not->toBeNull()
        ->and($field->context)->toBe(CustomField::CONTEXT_FORM)
        ->and($field->fieldable_type)->toBe(Form::class)
        ->and($field->fieldable_id)->toBe($form->id)
        ->and($field->getTranslations('label'))->toBe(['en' => 'Ticket type'])
        ->and($field->getTranslations('placeholder'))->toBe(['en' => 'Pick one'])
        ->and($field->options)->toBe([['value' => 'visitor', 'label' => 'Visitor (Free)']])
        ->and($field->validation)->toBe(['required' => true])
        ->and($field->order_column)->toBe(3);
});

it('backfills business-matching fields with canonical options and remaps field responses', function () {
    $event = Event::factory()->create();
    $user = User::factory()->create();

    $legacyId = DB::table('event_custom_fields')->insertGetId([
        'event_id' => $event->id,
        'label' => json_encode(['en' => 'Company name', 'id' => 'Nama perusahaan']),
        'type' => 'select',
        'options' => json_encode(['Distributor', 'Retailer']),
        'required' => true,
        'is_active' => true,
        'order_column' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('field_responses')->insert([
        'user_id' => $user->id,
        'event_custom_field_id' => $legacyId,
        'value' => json_encode(['Distributor']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(CustomFieldMigrator::class)->run();

    $field = CustomField::query()
        ->where('context', CustomField::CONTEXT_BUSINESS_MATCHING)
        ->where('legacy_id', $legacyId)
        ->first();

    expect($field)->not->toBeNull()
        ->and($field->fieldable_type)->toBe(Event::class)
        ->and($field->fieldable_id)->toBe($event->id)
        ->and($field->getTranslations('label'))->toBe(['en' => 'Company name', 'id' => 'Nama perusahaan'])
        ->and($field->options)->toBe([
            ['value' => 'Distributor', 'label' => 'Distributor'],
            ['value' => 'Retailer', 'label' => 'Retailer'],
        ])
        ->and($field->validation['required'])->toBeTrue();

    $this->assertDatabaseHas('custom_field_values', [
        'custom_field_id' => $field->id,
        'subject_type' => User::class,
        'subject_id' => $user->id,
    ]);
});

it('carries soft-deleted business-matching fields so old answers stay resolvable', function () {
    $event = Event::factory()->create();

    $legacyId = DB::table('event_custom_fields')->insertGetId([
        'event_id' => $event->id,
        'label' => json_encode(['en' => 'Removed question']),
        'type' => 'text',
        'required' => false,
        'is_active' => false,
        'created_at' => now(),
        'updated_at' => now(),
        'deleted_at' => now(),
    ]);

    app(CustomFieldMigrator::class)->run();

    $field = CustomField::withTrashed()
        ->where('context', CustomField::CONTEXT_BUSINESS_MATCHING)
        ->where('legacy_id', $legacyId)
        ->first();

    expect($field)->not->toBeNull()
        ->and($field->trashed())->toBeTrue();
});

it('backfills brand fields keeping the storage key verbatim and mapping year_select to the years preset', function () {
    $project = Project::factory()->create();

    $yearId = DB::table('project_custom_fields')->insertGetId([
        'project_id' => $project->id,
        'label' => 'Established year',
        'key' => 'established_year',
        'type' => 'year_select',
        'options' => null,
        'is_required' => true,
        'order_column' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(CustomFieldMigrator::class)->run();

    $field = CustomField::query()
        ->where('context', CustomField::CONTEXT_BRAND)
        ->where('legacy_id', $yearId)
        ->first();

    expect($field)->not->toBeNull()
        ->and($field->fieldable_type)->toBe(Project::class)
        ->and($field->key)->toBe('established_year')
        ->and($field->type)->toBe(CustomField::TYPE_SELECT)
        ->and($field->settings)->toBe(['options_preset' => 'years'])
        ->and($field->validation['required'])->toBeTrue();

    $years = FormFieldTypes::optionValues($field);

    expect($years)->toContain((string) now()->year)
        ->and($years)->toContain('1990');
});

it('synthesizes document mini-form fields and backfills submission values', function () {
    $document = EventDocument::factory()->create([
        'document_type' => 'checkbox_agreement',
        'is_required' => true,
    ]);
    $user = User::factory()->create();

    $submissionId = DB::table('event_document_submissions')->insertGetId([
        'ulid' => (string) Str::ulid(),
        'event_document_id' => $document->id,
        'booth_identifier' => 'A-01',
        'event_id' => $document->event_id,
        'agreed_at' => now(),
        'document_version' => 1,
        'submitted_by' => $user->id,
        'submitted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(CustomFieldMigrator::class)->run();

    $field = CustomField::query()
        ->where('context', CustomField::CONTEXT_DOCUMENT)
        ->where('legacy_id', $document->id)
        ->first();

    expect($field)->not->toBeNull()
        ->and($field->fieldable_type)->toBe(EventDocument::class)
        ->and($field->type)->toBe(CustomField::TYPE_CHECKBOX)
        ->and($field->system_key)->toBe('agreement')
        ->and($field->validation['required'])->toBeTrue();

    $values = json_decode((string) DB::table('event_document_submissions')->where('id', $submissionId)->value('field_values'), true);

    expect($values)->toBe([$field->ulid => true]);
});

it('is idempotent across re-runs', function () {
    $form = Form::factory()->create();
    $event = Event::factory()->create();

    DB::table('form_fields')->insert([
        'ulid' => (string) Str::ulid(),
        'form_id' => $form->id,
        'type' => 'text',
        'label' => 'Name',
        'order_column' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('event_custom_fields')->insert([
        'event_id' => $event->id,
        'label' => json_encode(['en' => 'Interest']),
        'type' => 'text',
        'required' => false,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migrator = app(CustomFieldMigrator::class);
    $migrator->run();
    $countAfterFirst = CustomField::withTrashed()->count();

    $migrator->run();

    expect(CustomField::withTrashed()->count())->toBe($countAfterFirst);
});

it('enforces system_key uniqueness per owner and context', function () {
    $event = Event::factory()->create();

    CustomField::factory()->ticketRegistration($event)->create(['system_key' => 'gender']);

    expect(fn () => CustomField::factory()->ticketRegistration($event)->create(['system_key' => 'gender']))
        ->toThrow(QueryException::class);
});

it('allows the same brand key on different projects but not within one project', function () {
    $projectA = Project::factory()->create();
    $projectB = Project::factory()->create();

    CustomField::factory()->brand($projectA)->create(['key' => 'founded']);
    CustomField::factory()->brand($projectB)->create(['key' => 'founded']);

    expect(CustomField::query()->where('key', 'founded')->count())->toBe(2)
        ->and(fn () => CustomField::factory()->brand($projectA)->create(['key' => 'founded']))
        ->toThrow(QueryException::class);
});
