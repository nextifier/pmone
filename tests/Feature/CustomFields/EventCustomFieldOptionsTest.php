<?php

use App\Models\ApiConsumer;
use App\Models\CustomField;
use App\Models\Event;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    ApiConsumer::factory()->create(['api_key' => 'pk_cf_options']);
    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create([
        'project_id' => $this->project->id,
        'tickets_enabled' => true,
    ]);
    $this->staff = User::factory()->create(['email_verified_at' => now()]);
    $this->staff->assignRole('staff');
});

/**
 * Enable a predefined library field without redeclaring the helper defined in
 * PredefinedCustomFieldsTest (top-level functions are shared across Pest files).
 */
function enablePredefined(string $systemKey, string $context = 'ticket_registration')
{
    return test()->actingAs(test()->staff)->putJson(
        '/api/events/'.test()->event->id.'/custom-fields/predefined/'.$systemKey,
        ['context' => $context, 'enabled' => true],
    )->assertOk();
}

it('serializes predefined select options as {value, label} objects with the full locale map', function () {
    enablePredefined('gender');

    $response = $this->actingAs($this->staff)
        ->getJson("/api/events/{$this->event->id}/custom-fields?context=ticket_registration")
        ->assertOk()
        ->assertJsonPath('data.0.system_key', 'gender')
        ->assertJsonPath('data.0.type', 'select');

    $options = $response->json('data.0.options');

    expect($options)->toBeArray()->toHaveCount(3);

    foreach ($options as $option) {
        expect($option)->toHaveKeys(['value', 'label'])
            ->and($option['value'])->toBeString()
            ->and($option['label'])->toBeArray()
            ->toHaveKeys(['en', 'id', 'ja', 'ko', 'zh']);
    }

    // The value keys are the stable answer keys; labels are the display strings.
    expect(collect($options)->pluck('value')->all())
        ->toEqual(['male', 'female', 'prefer_not_to_say']);
});

it('serializes a preset-backed field with an empty options list and the preset setting', function () {
    enablePredefined('birth_year');

    $this->actingAs($this->staff)
        ->getJson("/api/events/{$this->event->id}/custom-fields?context=ticket_registration")
        ->assertOk()
        ->assertJsonPath('data.0.system_key', 'birth_year')
        ->assertJsonPath('data.0.type', 'select')
        ->assertJsonPath('data.0.settings.options_preset', 'years')
        ->assertJsonPath('data.0.options', []);
});

it('preserves every locale label when a predefined field is updated without options', function () {
    enablePredefined('gender');

    $field = CustomField::query()->where('system_key', 'gender')->firstOrFail();
    $originalOptions = $field->options;

    // The admin editor sends no `options` for predefined fields (they are read-only).
    $this->actingAs($this->staff)
        ->putJson("/api/events/{$this->event->id}/custom-fields/{$field->id}", [
            'context' => 'ticket_registration',
            'label' => ['en' => 'Sex'],
            'type' => 'select',
        ])
        ->assertOk();

    $fresh = $field->fresh();

    expect($fresh->getTranslation('label', 'en'))->toBe('Sex')
        ->and($fresh->options)->toEqual($originalOptions);

    // The multi-language option labels survive intact - no [object Object] corruption.
    $male = collect($fresh->options)->firstWhere('value', 'male');
    expect($male['label'])->toMatchArray(['en' => 'Male', 'id' => 'Laki-laki']);
});

it('round-trips a custom select field without corrupting its options', function () {
    $created = $this->actingAs($this->staff)
        ->postJson("/api/events/{$this->event->id}/custom-fields", [
            'context' => 'ticket_registration',
            'label' => ['en' => 'Favorite'],
            'type' => 'select',
            'options' => ['Alpha', 'Beta'],
        ])
        ->assertCreated();

    $field = CustomField::query()->where('id', $created->json('data.id'))->firstOrFail();

    expect($field->system_key)->toBeNull()
        ->and($field->options)->toEqual([
            ['value' => 'Alpha', 'label' => 'Alpha'],
            ['value' => 'Beta', 'label' => 'Beta'],
        ]);

    // Re-save an edited option back as plain strings, the way the editor does.
    $this->actingAs($this->staff)
        ->putJson("/api/events/{$this->event->id}/custom-fields/{$field->id}", [
            'context' => 'ticket_registration',
            'label' => ['en' => 'Favorite'],
            'type' => 'select',
            'options' => ['Alpha', 'Beta 2'],
        ])
        ->assertOk();

    $fresh = $field->fresh();

    expect($fresh->options)->toEqual([
        ['value' => 'Alpha', 'label' => 'Alpha'],
        ['value' => 'Beta 2', 'label' => 'Beta 2'],
    ])
        ->and(collect($fresh->options)->pluck('value'))->not->toContain('[object Object]');
});

it('keeps a predefined field type locked even if a different type is submitted', function () {
    enablePredefined('gender');

    $field = CustomField::query()->where('system_key', 'gender')->firstOrFail();

    $this->actingAs($this->staff)
        ->putJson("/api/events/{$this->event->id}/custom-fields/{$field->id}", [
            'context' => 'ticket_registration',
            'label' => ['en' => 'Gender'],
            'type' => 'text',
        ])
        ->assertOk();

    expect($field->fresh()->type)->toBe('select');
});
