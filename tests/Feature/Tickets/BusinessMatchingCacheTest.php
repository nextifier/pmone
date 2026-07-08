<?php

use App\Models\CustomField;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\ResponseCache\Facades\ResponseCache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->project = Project::factory()->create();
    $this->seed(RoleAndPermissionSeeder::class);
    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole('master');
    $this->actingAs($this->admin);
});

it('busts the tickets cache when a custom field is created', function () {
    $event = bmEvent($this->project, true);

    ResponseCache::spy();

    $this->postJson("/api/events/{$event->id}/custom-fields", [
        'label' => ['en' => 'Company name'],
        'type' => 'text',
    ])->assertCreated();

    ResponseCache::shouldHaveReceived('clear')->with(['tickets']);
});

it('busts the tickets cache when a custom field is updated', function () {
    $event = bmEvent($this->project, true);
    $field = CustomField::factory()->create(['event_id' => $event->id, 'type' => 'text']);

    ResponseCache::spy();

    $this->putJson("/api/events/{$event->id}/custom-fields/{$field->id}", [
        'label' => ['en' => 'Updated label'],
        'type' => 'text',
    ])->assertOk();

    ResponseCache::shouldHaveReceived('clear')->with(['tickets']);
});

it('busts the tickets cache when custom fields are reordered', function () {
    $event = bmEvent($this->project, true);
    $a = CustomField::factory()->create(['event_id' => $event->id, 'type' => 'text']);
    $b = CustomField::factory()->create(['event_id' => $event->id, 'type' => 'text']);

    ResponseCache::spy();

    $this->postJson("/api/events/{$event->id}/custom-fields/reorder", [
        'orders' => [
            ['id' => $a->id, 'order' => 2],
            ['id' => $b->id, 'order' => 1],
        ],
    ])->assertOk();

    ResponseCache::shouldHaveReceived('clear')->with(['tickets']);
});

it('busts the tickets cache when business matching is toggled via ticket settings', function () {
    $event = bmEvent($this->project, false);

    ResponseCache::spy();

    $this->putJson("/api/events/{$event->id}/ticket-settings", [
        'business_matching_enabled' => true,
    ])->assertOk();

    ResponseCache::shouldHaveReceived('clear')->with(['tickets']);
});
