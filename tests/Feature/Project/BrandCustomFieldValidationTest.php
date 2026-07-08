<?php

use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\CustomField;
use App\Models\Event;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['projects.read', 'projects.update', 'brands.read', 'brands.update'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $masterRole = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $masterRole->syncPermissions(Permission::all());

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);

    $this->project = Project::factory()->create(['username' => 'acme']);
    $this->event = Event::factory()->create([
        'project_id' => $this->project->id,
        'slug' => 'expo-2026',
    ]);

    $this->brand = Brand::factory()->create();
    BrandEvent::factory()->create([
        'brand_id' => $this->brand->id,
        'event_id' => $this->event->id,
    ]);

    $this->fieldsUrl = '/api/projects/acme/custom-fields';
    $this->profileUrl = "/api/projects/acme/events/expo-2026/brands/{$this->brand->slug}/profile";
});

function brandSelectField(Project $project): CustomField
{
    return CustomField::factory()->brand($project)->create([
        'type' => CustomField::TYPE_SELECT,
        'label' => ['en' => 'Franchise Type'],
        'key' => 'franchise_type',
        'options' => [
            ['value' => 'Food', 'label' => 'Food'],
            ['value' => 'Retail', 'label' => 'Retail'],
        ],
    ]);
}

// ============================================================
// Definition CRUD (legacy routes, centralized storage)
// ============================================================

it('creates a brand field from the legacy plain-string payload', function () {
    $response = $this->postJson($this->fieldsUrl, [
        'label' => 'Business Concept',
        'type' => 'text',
        'options' => null,
        'is_required' => true,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.label', 'Business Concept')
        ->assertJsonPath('data.key', 'business_concept')
        ->assertJsonPath('data.type', 'text')
        ->assertJsonPath('data.is_required', true);

    $field = CustomField::query()->where('key', 'business_concept')->firstOrFail();

    expect($field->context)->toBe(CustomField::CONTEXT_BRAND)
        ->and($field->fieldable_type)->toBe(Project::class)
        ->and($field->fieldable_id)->toBe($this->project->id)
        ->and($field->getTranslations('label'))->toBe(['en' => 'Business Concept'])
        ->and($field->validation['required'])->toBeTrue();
});

it('normalizes select options and returns them as plain strings', function () {
    $response = $this->postJson($this->fieldsUrl, [
        'label' => 'Franchise Type',
        'type' => 'select',
        'options' => ['Food', 'Retail'],
        'is_required' => false,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.options', ['Food', 'Retail']);

    $field = CustomField::query()->where('key', 'franchise_type')->firstOrFail();

    expect($field->options)->toBe([
        ['value' => 'Food', 'label' => 'Food'],
        ['value' => 'Retail', 'label' => 'Retail'],
    ]);
});

it('coerces the year_select alias to a preset-backed select but keeps exposing year_select', function () {
    $response = $this->postJson($this->fieldsUrl, [
        'label' => 'Established Year',
        'type' => 'year_select',
        'options' => null,
        'is_required' => false,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.type', 'year_select')
        ->assertJsonPath('data.options', null);

    $field = CustomField::query()->where('key', 'established_year')->firstOrFail();

    expect($field->type)->toBe(CustomField::TYPE_SELECT)
        ->and($field->settings['options_preset'])->toBe('years');

    $this->getJson($this->fieldsUrl)
        ->assertSuccessful()
        ->assertJsonPath('data.0.type', 'year_select');
});

it('rejects types outside the brand whitelist', function () {
    $this->postJson($this->fieldsUrl, [
        'label' => 'Attachment',
        'type' => 'file',
    ])->assertStatus(422)->assertJsonValidationErrors(['type']);
});

it('keeps the storage key immutable when the label changes', function () {
    $fieldId = $this->postJson($this->fieldsUrl, [
        'label' => 'Business Concept',
        'type' => 'text',
        'options' => null,
        'is_required' => false,
    ])->assertStatus(201)->json('data.id');

    $response = $this->putJson("{$this->fieldsUrl}/{$fieldId}", [
        'label' => 'Concept Story',
        'type' => 'text',
        'options' => null,
        'is_required' => true,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.key', 'business_concept')
        ->assertJsonPath('data.label', 'Concept Story')
        ->assertJsonPath('data.is_required', true);
});

it('reorders fields through the service', function () {
    $first = CustomField::factory()->brand($this->project)->create(['label' => ['en' => 'Alpha'], 'key' => 'alpha']);
    $second = CustomField::factory()->brand($this->project)->create(['label' => ['en' => 'Beta'], 'key' => 'beta']);

    $this->putJson("{$this->fieldsUrl}/reorder", [
        'orders' => [
            ['id' => $second->id, 'order' => 1],
            ['id' => $first->id, 'order' => 2],
        ],
    ])->assertSuccessful();

    $this->getJson($this->fieldsUrl)
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $second->id)
        ->assertJsonPath('data.1.id', $first->id);
});

it('rejects reordering fields that belong to another project', function () {
    $foreign = CustomField::factory()->brand(Project::factory()->create())->create();

    $this->putJson("{$this->fieldsUrl}/reorder", [
        'orders' => [['id' => $foreign->id, 'order' => 1]],
    ])->assertStatus(422);
});

it('blocks users without project permissions', function () {
    $viewer = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($viewer);

    $this->getJson($this->fieldsUrl)->assertForbidden();
    $this->postJson($this->fieldsUrl, ['label' => 'X', 'type' => 'text'])->assertForbidden();

    $viewer->givePermissionTo('projects.read');

    $this->getJson($this->fieldsUrl)->assertSuccessful();
    $this->postJson($this->fieldsUrl, ['label' => 'X', 'type' => 'text'])->assertForbidden();
});

// ============================================================
// Value enforcement (only when the payload carries the key)
// ============================================================

it('rejects an invalid select value when brand-field values are submitted', function () {
    brandSelectField($this->project);

    $this->putJson($this->profileUrl, [
        'project_custom_fields' => ['franchise_type' => 'Bogus'],
    ])->assertStatus(422)->assertJsonValidationErrors(['project_custom_fields.franchise_type']);

    expect($this->brand->fresh()->custom_fields)->toBeEmpty();
});

it('rejects a blank required value when the payload carries brand fields', function () {
    CustomField::factory()->brand($this->project)->required()->create([
        'label' => ['en' => 'Business Concept'],
        'key' => 'business_concept',
    ]);

    $this->putJson($this->profileUrl, [
        'project_custom_fields' => ['business_concept' => ''],
    ])->assertStatus(422)->assertJsonValidationErrors(['project_custom_fields.business_concept']);

    $this->putJson($this->profileUrl, [
        'project_custom_fields' => [],
    ])->assertStatus(422)->assertJsonValidationErrors(['project_custom_fields.business_concept']);
});

it('skips value validation when the payload omits brand fields', function () {
    CustomField::factory()->brand($this->project)->required()->create([
        'label' => ['en' => 'Business Concept'],
        'key' => 'business_concept',
    ]);

    $this->putJson($this->profileUrl, [
        'name' => 'Renamed Brand',
    ])->assertSuccessful();

    expect($this->brand->fresh()->name)->toBe('Renamed Brand');
});

it('saves valid values and drops keys without a definition', function () {
    brandSelectField($this->project);

    $this->putJson($this->profileUrl, [
        'project_custom_fields' => [
            'franchise_type' => 'Food',
            'rogue_key' => 'nope',
        ],
    ])->assertSuccessful();

    $values = $this->brand->fresh()->custom_fields;

    expect($values['franchise_type'])->toBe('Food')
        ->and($values)->not->toHaveKey('rogue_key');
});

it('validates year-preset selects against the generated years list', function () {
    CustomField::factory()->brand($this->project)->create([
        'type' => CustomField::TYPE_SELECT,
        'label' => ['en' => 'Established Year'],
        'key' => 'established_year',
        'options' => null,
        'settings' => ['options_preset' => 'years'],
    ]);

    $this->putJson($this->profileUrl, [
        'project_custom_fields' => ['established_year' => '1800'],
    ])->assertStatus(422)->assertJsonValidationErrors(['project_custom_fields.established_year']);

    $this->putJson($this->profileUrl, [
        'project_custom_fields' => ['established_year' => '2020'],
    ])->assertSuccessful();

    expect($this->brand->fresh()->custom_fields['established_year'])->toBe('2020');
});

it('enforces values on the global brand update endpoint too', function () {
    brandSelectField($this->project);

    $this->putJson("/api/brands/{$this->brand->slug}", [
        'project_custom_fields' => ['franchise_type' => 'Bogus'],
    ])->assertStatus(422)->assertJsonValidationErrors(['project_custom_fields.franchise_type']);

    $this->putJson("/api/brands/{$this->brand->slug}", [
        'project_custom_fields' => ['franchise_type' => 'Retail'],
    ])->assertSuccessful();

    expect($this->brand->fresh()->custom_fields['franchise_type'])->toBe('Retail');
});

// ============================================================
// Definition exposure for the exhibitor profile form
// ============================================================

it('exposes legacy-shaped definitions on the brand-event pages', function () {
    brandSelectField($this->project);
    CustomField::factory()->brand($this->project)->create([
        'type' => CustomField::TYPE_SELECT,
        'label' => ['en' => 'Established Year'],
        'key' => 'established_year',
        'options' => null,
        'settings' => ['options_preset' => 'years'],
    ]);

    $response = $this->getJson("/api/projects/acme/events/expo-2026/brands/{$this->brand->slug}");

    $response->assertSuccessful()
        ->assertJsonPath('project_custom_field_definitions.0.key', 'franchise_type')
        ->assertJsonPath('project_custom_field_definitions.0.type', 'select')
        ->assertJsonPath('project_custom_field_definitions.0.options', ['Food', 'Retail'])
        ->assertJsonPath('project_custom_field_definitions.0.is_required', false)
        ->assertJsonPath('project_custom_field_definitions.1.key', 'established_year')
        ->assertJsonPath('project_custom_field_definitions.1.type', 'year_select')
        ->assertJsonPath('project_custom_field_definitions.1.options', null);
});
