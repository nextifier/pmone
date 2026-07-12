<?php

use App\Models\ApiConsumer;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('master');
    $this->actingAs($this->admin);
});

it('shows the raw key exactly once on create and never again on show', function () {
    $createResponse = $this->postJson('/api/api-consumers', [
        'name' => 'Test Consumer',
        'website_url' => 'https://example.com',
        'rate_limit' => 60,
        'is_active' => true,
    ]);

    $createResponse->assertCreated();
    $createResponse->assertJsonStructure(['data', 'key']);

    $rawKey = $createResponse->json('key');
    expect($rawKey)->toStartWith('pk_');

    $consumerId = $createResponse->json('data.id');

    // The create response's `data` object must never carry the key.
    expect($createResponse->json('data'))->not->toHaveKey('api_key');

    $showResponse = $this->getJson("/api/api-consumers/{$consumerId}");
    $showResponse->assertSuccessful();
    $showResponse->assertJsonMissingPath('data.api_key');
    $showResponse->assertJsonMissingPath('data.api_key_hash');
    expect($showResponse->json('key'))->toBeNull();
});

it('shows the raw key exactly once on regenerate', function () {
    $consumer = ApiConsumer::factory()->create(['api_key' => 'pk_test_before_regenerate']);

    $response = $this->postJson("/api/api-consumers/{$consumer->id}/regenerate-key");

    $response->assertSuccessful();
    $newKey = $response->json('key');

    expect($newKey)->toStartWith('pk_')
        ->and($newKey)->not->toBe('pk_test_before_regenerate');

    $showResponse = $this->getJson("/api/api-consumers/{$consumer->id}");
    $showResponse->assertJsonMissingPath('data.api_key');
    expect($showResponse->json('key'))->toBeNull();
});

it('saves and returns the project scope selection', function () {
    $project = Project::factory()->create(['username' => 'scoped-project']);

    $createResponse = $this->postJson('/api/api-consumers', [
        'name' => 'Scoped Consumer',
        'website_url' => 'https://scoped.example.com',
        'rate_limit' => 60,
        'is_active' => true,
        'project_ids' => [$project->id],
    ]);

    $createResponse->assertCreated();
    $consumerId = $createResponse->json('data.id');

    $consumer = ApiConsumer::findOrFail($consumerId);
    expect($consumer->projects()->pluck('projects.id')->all())->toBe([$project->id]);

    $showResponse = $this->getJson("/api/api-consumers/{$consumerId}");
    $showResponse->assertJsonPath('data.projects.0.id', $project->id);
});
