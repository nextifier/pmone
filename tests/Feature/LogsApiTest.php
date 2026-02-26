<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create roles for testing
    Role::create(['name' => 'user']);
    Role::create(['name' => 'admin']);
    Role::create(['name' => 'master']);
});

it('requires authentication to access logs', function () {
    $response = $this->getJson('/api/logs');

    $response->assertUnauthorized();
});

it('requires master or admin role to access logs', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/logs');

    $response->assertForbidden()
        ->assertJson([
            'message' => 'Unauthorized. Only master and admin roles can access logs.',
        ]);
});

it('allows admin role to access logs', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/logs');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data',
            'meta' => [
                'current_page',
                'per_page',
                'total',
                'last_page',
            ],
        ]);
});

it('allows master role to access logs', function () {
    $user = User::factory()->create();
    $user->assignRole('master');

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/logs');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data',
            'meta' => [
                'current_page',
                'per_page',
                'total',
                'last_page',
            ],
        ]);
});

it('can filter logs by log name', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/logs?log_name=default');

    $response->assertSuccessful();
});

it('can filter logs by event', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/logs?event=updated');

    $response->assertSuccessful();
});

it('can search logs', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/logs?search=updated');

    $response->assertSuccessful();
});

it('can paginate logs', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/logs?page=1&per_page=10');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data',
            'meta' => [
                'current_page',
                'per_page',
                'total',
                'last_page',
            ],
        ]);
});

it('returns log names for authorized users', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/logs/log-names');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data',
        ]);
});

it('returns events for authorized users', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/logs/events');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data',
        ]);
});

it('requires master role to clear logs', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    Sanctum::actingAs($user);

    $response = $this->deleteJson('/api/logs/clear');

    $response->assertForbidden()
        ->assertJson([
            'message' => 'Unauthorized. Only master role can clear logs.',
        ]);
});

it('allows master role to clear logs', function () {
    $user = User::factory()->create();
    $user->assignRole('master');

    Sanctum::actingAs($user);

    $response = $this->deleteJson('/api/logs/clear');

    $response->assertSuccessful()
        ->assertJson([
            'message' => 'Logs cleared successfully',
        ]);

    // Verify activity logs table has a new clearing log entry
    expect(\Spatie\Activitylog\Models\Activity::where('description', 'Activity logs cleared')->count())->toBe(1);
});

it('returns empty data when no logs match search', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/logs?search=nonexistentsearchterm12345');

    $response->assertSuccessful()
        ->assertJson([
            'data' => [],
            'meta' => [
                'total' => 0,
            ],
        ]);
});

it('includes human readable descriptions in response', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/logs');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'human_description',
                    'subject_name',
                    'time_ago',
                ],
            ],
        ]);

    // Check that human_description and subject_name are present
    $logs = $response->json('data');
    if (count($logs) > 0) {
        expect($logs[0]['human_description'])->toBeString();
        expect($logs[0]['time_ago'])->toBeString();
        // subject_name can be null or string
        expect($logs[0])->toHaveKey('subject_name');
    }
});
