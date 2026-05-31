<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['master', 'admin', 'staff', 'writer', 'user', 'marketing'] as $name) {
        Role::findOrCreate($name, 'web');
    }
});

function actingAsUserWithRole(string $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);
    test()->actingAs($user);

    return $user;
}

it('shows a staff user only the projects they are a member of', function () {
    $user = actingAsUserWithRole('staff');

    $memberProject = Project::factory()->create(['status' => 'active']);
    $otherProject = Project::factory()->create(['status' => 'active']);

    $user->projects()->attach($memberProject->id);

    $response = $this->getJson('/api/dashboard/stats');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data.my_projects')
        ->assertJsonPath('data.my_projects.0.id', $memberProject->id);

    $usernames = collect($response->json('data.my_projects'))->pluck('username');
    expect($usernames)->toContain($memberProject->username);
    expect($usernames)->not->toContain($otherProject->username);
});

it('shows an admin user all active projects even when not a member', function () {
    actingAsUserWithRole('admin');

    $projectOne = Project::factory()->create(['status' => 'active']);
    $projectTwo = Project::factory()->create(['status' => 'active']);

    $response = $this->getJson('/api/dashboard/stats');

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data.my_projects');

    $ids = collect($response->json('data.my_projects'))->pluck('id');
    expect($ids)->toContain($projectOne->id)->toContain($projectTwo->id);
});

it('shows a master user all active projects even when not a member', function () {
    actingAsUserWithRole('master');

    $projectOne = Project::factory()->create(['status' => 'active']);
    $projectTwo = Project::factory()->create(['status' => 'active']);

    $response = $this->getJson('/api/dashboard/stats');

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data.my_projects');

    $ids = collect($response->json('data.my_projects'))->pluck('id');
    expect($ids)->toContain($projectOne->id)->toContain($projectTwo->id);
});

it('excludes non-active projects from an admin user view', function () {
    actingAsUserWithRole('admin');

    $activeProject = Project::factory()->create(['status' => 'active']);
    Project::factory()->create(['status' => 'archived']);

    $response = $this->getJson('/api/dashboard/stats');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data.my_projects')
        ->assertJsonPath('data.my_projects.0.id', $activeProject->id);
});

it('excludes non-active projects a staff user is a member of', function () {
    $user = actingAsUserWithRole('staff');

    $activeMemberProject = Project::factory()->create(['status' => 'active']);
    $archivedMemberProject = Project::factory()->create(['status' => 'archived']);

    $user->projects()->attach([$activeMemberProject->id, $archivedMemberProject->id]);

    $response = $this->getJson('/api/dashboard/stats');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data.my_projects')
        ->assertJsonPath('data.my_projects.0.id', $activeMemberProject->id);
});
