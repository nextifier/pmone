<?php

use App\Models\CustomField;
use App\Models\Event;
use App\Models\EventDocument;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Covers the shared field editor payload: placeholder/help text translations,
 * per-type validation and settings surviving an edit, and the resources that
 * feed the admin dialogs.
 */
beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);

    $this->project = Project::factory()->create(['username' => 'acme']);
    $this->event = Event::factory()->create([
        'project_id' => $this->project->id,
        'slug' => 'expo-2026',
        'tickets_enabled' => true,
    ]);

    $this->document = EventDocument::factory()->create([
        'event_id' => $this->event->id,
        'document_type' => 'custom',
    ]);

    $this->eventFieldsUrl = "/api/events/{$this->event->id}/custom-fields";
    $this->brandFieldsUrl = '/api/projects/acme/custom-fields';
    $this->documentFieldsUrl = "/api/projects/acme/events/expo-2026/documents/{$this->document->ulid}/fields";
});

// ============================================================
// Business matching / ticket registration
// ============================================================

it('round-trips placeholder and help text translations and drops the blank locales', function () {
    $created = $this->postJson($this->eventFieldsUrl, [
        'context' => 'business_matching',
        'label' => ['en' => 'Company name', 'id' => 'Nama perusahaan'],
        'type' => 'text',
        'placeholder' => ['en' => 'e.g. Acme Inc', 'id' => 'mis. Acme Inc', 'ja' => '', 'ko' => '', 'zh' => ''],
        'help_text' => ['en' => 'As registered', 'id' => '', 'ja' => '', 'ko' => '', 'zh' => ''],
        'validation' => ['required' => true],
        'settings' => [],
    ])->assertCreated();

    $field = CustomField::query()->findOrFail($created->json('data.id'));

    expect($field->getTranslations('placeholder'))->toBe([
        'en' => 'e.g. Acme Inc',
        'id' => 'mis. Acme Inc',
    ])
        ->and($field->getTranslations('help_text'))->toBe(['en' => 'As registered']);

    $this->getJson("{$this->eventFieldsUrl}?context=business_matching")
        ->assertOk()
        ->assertJsonPath('data.0.placeholder', 'e.g. Acme Inc')
        ->assertJsonPath('data.0.placeholder_translations.id', 'mis. Acme Inc')
        ->assertJsonPath('data.0.help_text_translations.en', 'As registered');
});

it('clears a single placeholder locale when the editor blanks it out', function () {
    $created = $this->postJson($this->eventFieldsUrl, [
        'context' => 'business_matching',
        'label' => ['en' => 'Company name'],
        'type' => 'text',
        'placeholder' => ['en' => 'Acme', 'id' => 'Halo'],
    ])->assertCreated();

    $fieldId = $created->json('data.id');

    $this->putJson("{$this->eventFieldsUrl}/{$fieldId}", [
        'context' => 'business_matching',
        'label' => ['en' => 'Company name'],
        'type' => 'text',
        'placeholder' => ['en' => 'Acme', 'id' => '', 'ja' => '', 'ko' => '', 'zh' => ''],
    ])->assertOk();

    expect(CustomField::query()->findOrFail($fieldId)->getTranslations('placeholder'))
        ->toBe(['en' => 'Acme']);
});

it('keeps min and max when a business matching field is edited', function () {
    $created = $this->postJson($this->eventFieldsUrl, [
        'context' => 'business_matching',
        'label' => ['en' => 'Pitch'],
        'type' => 'textarea',
        'validation' => ['required' => true, 'min' => 2, 'max' => 10],
    ])->assertCreated();

    $fieldId = $created->json('data.id');

    // The editor always sends the whole validation map back, never the legacy
    // top-level `required` flag that made the backend rebuild it from scratch.
    $this->putJson("{$this->eventFieldsUrl}/{$fieldId}", [
        'context' => 'business_matching',
        'label' => ['en' => 'Elevator pitch'],
        'type' => 'textarea',
        'validation' => ['required' => true, 'min' => 2, 'max' => 10],
        'settings' => [],
    ])->assertOk();

    expect(CustomField::query()->findOrFail($fieldId)->validation)
        ->toMatchArray(['required' => true, 'min' => 2, 'max' => 10]);
});

it('still accepts the legacy top-level required flag', function () {
    $created = $this->postJson($this->eventFieldsUrl, [
        'context' => 'business_matching',
        'label' => ['en' => 'Website'],
        'type' => 'url',
    ])->assertCreated();

    $fieldId = $created->json('data.id');

    $this->putJson("{$this->eventFieldsUrl}/{$fieldId}", [
        'context' => 'business_matching',
        'label' => ['en' => 'Website'],
        'type' => 'url',
        'required' => true,
    ])->assertOk();

    expect(CustomField::query()->findOrFail($fieldId)->validation['required'])->toBeTrue();
});

it('keeps the options preset when a predefined field gets edited', function () {
    $this->putJson("{$this->eventFieldsUrl}/predefined/birth_year", [
        'context' => 'ticket_registration',
        'enabled' => true,
    ])->assertOk();

    $field = CustomField::query()->where('system_key', 'birth_year')->firstOrFail();

    $this->putJson("{$this->eventFieldsUrl}/{$field->id}", [
        'context' => 'ticket_registration',
        'label' => ['en' => 'Year of birth'],
        'type' => 'select',
        'help_text' => ['en' => 'Used for age brackets'],
        'validation' => ['required' => true],
        // The editor carries unmanaged settings keys straight back.
        'settings' => ['options_preset' => 'years'],
    ])->assertOk();

    $fresh = $field->fresh();

    expect($fresh->settings['options_preset'])->toBe('years')
        ->and($fresh->help_text)->toBe('Used for age brackets');
});

// ============================================================
// Brand fields
// ============================================================

it('exposes placeholder and help text on the brand field resource', function () {
    $this->postJson($this->brandFieldsUrl, [
        'label' => ['en' => 'Business concept', 'id' => 'Konsep bisnis'],
        'type' => 'textarea',
        'placeholder' => ['en' => 'Describe it', 'id' => 'Jelaskan'],
        'help_text' => ['en' => 'Two sentences is plenty'],
    ])->assertCreated();

    $this->getJson($this->brandFieldsUrl)
        ->assertOk()
        ->assertJsonPath('data.0.placeholder', 'Describe it')
        ->assertJsonPath('data.0.placeholder_translations.id', 'Jelaskan')
        ->assertJsonPath('data.0.help_text', 'Two sentences is plenty');
});

it('keeps settings.public intact while saving other brand field settings', function () {
    $created = $this->postJson($this->brandFieldsUrl, [
        'label' => ['en' => 'Satisfaction'],
        'type' => 'linear_scale',
    ])->assertCreated();

    $fieldId = $created->json('data.id');

    $this->putJson("{$this->brandFieldsUrl}/{$fieldId}", [
        'label' => ['en' => 'Satisfaction'],
        'type' => 'linear_scale',
        'is_public' => false,
        'validation' => ['required' => false, 'min' => 1, 'max' => 5],
        'settings' => ['min_label' => 'Low', 'max_label' => 'High'],
    ])->assertOk();

    $fresh = CustomField::query()->findOrFail($fieldId);

    expect($fresh->settings)->toMatchArray([
        'min_label' => 'Low',
        'max_label' => 'High',
        'public' => false,
    ])
        ->and($fresh->validation)->toMatchArray(['min' => 1, 'max' => 5]);
});

it('keeps brand field min and max when is_required is not sent', function () {
    $created = $this->postJson($this->brandFieldsUrl, [
        'label' => ['en' => 'Founded'],
        'type' => 'number',
        'validation' => ['required' => true, 'min' => 1900, 'max' => 2100],
    ])->assertCreated();

    $fieldId = $created->json('data.id');

    $this->putJson("{$this->brandFieldsUrl}/{$fieldId}", [
        'label' => ['en' => 'Founded in'],
        'type' => 'number',
        'is_public' => true,
        'validation' => ['required' => true, 'min' => 1900, 'max' => 2100],
        'settings' => [],
    ])->assertOk();

    $this->getJson($this->brandFieldsUrl)
        ->assertOk()
        ->assertJsonPath('data.0.is_required', true)
        ->assertJsonPath('data.0.validation.min', 1900)
        ->assertJsonPath('data.0.validation.max', 2100);
});

it('drops the years preset when a brand field leaves the year_select alias', function () {
    $created = $this->postJson($this->brandFieldsUrl, [
        'label' => ['en' => 'Founded'],
        'type' => 'year_select',
    ])->assertCreated();

    $fieldId = $created->json('data.id');

    expect(CustomField::query()->findOrFail($fieldId)->settings['options_preset'])->toBe('years');

    $this->putJson("{$this->brandFieldsUrl}/{$fieldId}", [
        'label' => ['en' => 'Founded'],
        'type' => 'select',
        'is_public' => true,
        'options' => ['1990s', '2000s'],
        'validation' => ['required' => false],
        'settings' => [],
    ])->assertOk();

    expect(CustomField::query()->findOrFail($fieldId)->settings)->not->toHaveKey('options_preset');

    $this->getJson($this->brandFieldsUrl)
        ->assertOk()
        ->assertJsonPath('data.0.type', 'select')
        ->assertJsonPath('data.0.options', ['1990s', '2000s']);
});

// ============================================================
// Ops document mini-forms
// ============================================================

it('stores the file configuration on a document field', function () {
    $created = $this->postJson($this->documentFieldsUrl, [
        'label' => ['en' => 'Company profile'],
        'type' => 'file',
        'help_text' => ['en' => 'PDF only'],
        'validation' => [
            'required' => true,
            'max_file_size' => 5120,
            'max_files' => 3,
            'allowed_file_types' => ['pdf'],
        ],
        'settings' => ['multiple' => true],
    ])->assertCreated();

    $created
        ->assertJsonPath('data.validation.max_file_size', 5120)
        ->assertJsonPath('data.validation.max_files', 3)
        ->assertJsonPath('data.validation.allowed_file_types', ['pdf'])
        ->assertJsonPath('data.settings.multiple', true)
        ->assertJsonPath('data.help_text_translations.en', 'PDF only');
});

it('keeps min and max when a document field is edited', function () {
    $created = $this->postJson($this->documentFieldsUrl, [
        'label' => ['en' => 'Tagline'],
        'type' => 'text',
        'validation' => ['required' => true, 'min' => 5, 'max' => 60],
    ])->assertCreated();

    $ulid = $created->json('data.ulid');

    $this->putJson("{$this->documentFieldsUrl}/{$ulid}", [
        'label' => ['en' => 'Tagline'],
        'placeholder' => ['en' => 'Short and punchy'],
        'type' => 'text',
        'validation' => ['required' => true, 'min' => 5, 'max' => 60],
        'settings' => [],
    ])->assertOk();

    $field = CustomField::query()->where('ulid', $ulid)->firstOrFail();

    expect($field->validation)->toMatchArray(['required' => true, 'min' => 5, 'max' => 60])
        ->and($field->placeholder)->toBe('Short and punchy');
});
