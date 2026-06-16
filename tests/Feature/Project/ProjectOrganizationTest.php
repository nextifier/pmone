<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $masterRole = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'projects.update', 'guard_name' => 'web']);
    $masterRole->syncPermissions(Permission::all());

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
});

it('leaves organization null for new projects when not provided', function () {
    $project = Project::factory()->create();

    expect($project->fresh()->organization)->toBeNull();
});

it('allows clearing organization back to null', function () {
    $this->actingAs($this->user);
    $project = Project::factory()->create(['organization' => 'CampX']);

    $response = $this->putJson("/api/projects/{$project->username}", [
        'organization' => null,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.organization', null);

    expect($project->fresh()->organization)->toBeNull();
});

it('updates a project organization to a known client', function () {
    $this->actingAs($this->user);
    $project = Project::factory()->create();

    $response = $this->putJson("/api/projects/{$project->username}", [
        'organization' => 'ASKINDO',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.organization', 'ASKINDO');

    expect($project->fresh()->organization)->toBe('ASKINDO');
});

it('accepts a custom organization value', function () {
    $this->actingAs($this->user);
    $project = Project::factory()->create(['organization' => 'Panorama Media']);

    $response = $this->putJson("/api/projects/{$project->username}", [
        'organization' => 'Acme Custom Co',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.organization', 'Acme Custom Co');

    expect($project->fresh()->organization)->toBe('Acme Custom Co');
});

it('exposes organization in the projects list resource', function () {
    $this->actingAs($this->user);
    Project::factory()->create(['organization' => 'CampX']);

    $response = $this->getJson('/api/projects');

    $response->assertSuccessful()
        ->assertJsonPath('data.0.organization', fn ($org) => is_string($org));
});
