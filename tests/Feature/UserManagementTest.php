<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed roles (RefreshDatabase already handles migration)
    $this->artisan('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
});

it('allows authenticated users to view users list', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/users');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'name',
                'email',
                'username',
                'status',
                'roles',
            ],
        ],
        'meta',
    ]);
});

it('allows master users to create new users', function () {
    $master = User::factory()->create();
    $master->assignRole('master');

    $userData = [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'status' => 'active',
        'visibility' => 'public',
        'roles' => ['user'],
    ];

    $response = $this->actingAs($master, 'sanctum')
        ->postJson('/api/users', $userData);

    $response->assertStatus(201);
    $response->assertJson([
        'message' => 'User created successfully',
    ]);

    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
        'name' => 'Test User',
    ]);
});

it('allows admin users to create new users', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $userData = [
        'name' => 'Test Admin User',
        'email' => 'testadmin@example.com',
        'password' => 'password123',
        'status' => 'active',
        'visibility' => 'public',
        'roles' => ['user'],
    ];

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/users', $userData);

    $response->assertStatus(201);
    $response->assertJson([
        'message' => 'User created successfully',
    ]);
});

it('prevents regular users from creating new users', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $userData = [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'roles' => ['user'],
    ];

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/users', $userData);

    $response->assertStatus(403);
});

it('allows master and admin users to update users', function () {
    $master = User::factory()->create();
    $master->assignRole('master');

    $targetUser = User::factory()->create();
    $targetUser->assignRole('user');

    $updateData = [
        'name' => 'Updated Name',
        'status' => 'inactive',
    ];

    $response = $this->actingAs($master, 'sanctum')
        ->putJson("/api/users/{$targetUser->username}", $updateData);

    $response->assertStatus(200);
    $response->assertJson([
        'message' => 'User updated successfully',
    ]);

    $targetUser->refresh();
    expect($targetUser->name)->toBe('Updated Name');
    expect($targetUser->status)->toBe('inactive');
});

it('allows master users to delete users', function () {
    $master = User::factory()->create();
    $master->assignRole('master');

    $targetUser = User::factory()->create();
    $targetUser->assignRole('user');

    $response = $this->actingAs($master, 'sanctum')
        ->deleteJson("/api/users/{$targetUser->username}");

    $response->assertStatus(200);
    $response->assertJson([
        'message' => 'User deleted successfully',
    ]);

    // User should be deleted from database
    $this->assertDatabaseMissing('users', [
        'id' => $targetUser->id,
    ]);
});

it('allows admin users to delete regular users', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $targetUser = User::factory()->create();
    $targetUser->assignRole('user');

    $response = $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/users/{$targetUser->username}");

    $response->assertStatus(200);
    $response->assertJson([
        'message' => 'User deleted successfully',
    ]);
});

it('validates required fields when creating users', function () {
    $master = User::factory()->create();
    $master->assignRole('master');

    $response = $this->actingAs($master, 'sanctum')
        ->postJson('/api/users', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name', 'email', 'password']);
});

it('can filter users by role', function () {
    $master = User::factory()->create();
    $master->assignRole('master');

    $adminUser = User::factory()->create();
    $adminUser->assignRole('admin');

    $regularUser = User::factory()->create();
    $regularUser->assignRole('user');

    $response = $this->actingAs($master, 'sanctum')
        ->getJson('/api/users?filter[role]=admin');

    $response->assertStatus(200);

    $userData = $response->json('data');
    expect(count($userData))->toBeGreaterThan(0);

    // Check that returned users have admin role
    foreach ($userData as $user) {
        expect($user['roles'])->toContain('admin');
    }
});

it('can search users by name and email', function () {
    $master = User::factory()->create();
    $master->assignRole('master');

    $searchableUser = User::factory()->create([
        'name' => 'John Searchable',
        'email' => 'searchable@example.com',
    ]);
    $searchableUser->assignRole('user');

    $response = $this->actingAs($master, 'sanctum')
        ->getJson('/api/users?filter[search]=Searchable');

    $response->assertStatus(200);

    $userData = $response->json('data');
    expect(count($userData))->toBeGreaterThan(0);

    // Check that the searchable user is in results
    $foundUser = collect($userData)->firstWhere('email', 'searchable@example.com');
    expect($foundUser)->not->toBeNull();
});

it('prevents admin from upgrading their own role to master', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $updateData = [
        'name' => 'Updated Admin Name',
        'status' => 'active',
        'roles' => ['master'], // Admin trying to change their own role to master
    ];

    $response = $this->actingAs($admin, 'sanctum')
        ->putJson("/api/users/{$admin->username}", $updateData);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['roles.0']);

    $admin->refresh();
    expect($admin->hasRole('master'))->toBeFalse();
    expect($admin->hasRole('admin'))->toBeTrue();
});

it('prevents admin from assigning master role', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $userData = [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'roles' => ['master'], // Admin trying to assign master role
    ];

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/users', $userData);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['roles.0']);
});

it('allows master to assign master role', function () {
    $master = User::factory()->create();
    $master->assignRole('master');

    $userData = [
        'name' => 'Test Master User',
        'email' => 'testmaster@example.com',
        'password' => 'password123',
        'roles' => ['master'],
    ];

    $response = $this->actingAs($master, 'sanctum')
        ->postJson('/api/users', $userData);

    $response->assertStatus(201);

    $newUser = User::where('email', 'testmaster@example.com')->first();
    expect($newUser->hasRole('master'))->toBeTrue();
});

it('prevents admin from editing master users', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $masterUser = User::factory()->create();
    $masterUser->assignRole('master');

    $updateData = [
        'name' => 'Updated Master Name',
    ];

    $response = $this->actingAs($admin, 'sanctum')
        ->putJson("/api/users/{$masterUser->username}", $updateData);

    $response->assertStatus(403);
    $response->assertJson([
        'message' => 'Only master users can edit other master users.',
    ]);
});

it('prevents admin from deleting master users', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $masterUser = User::factory()->create();
    $masterUser->assignRole('master');

    $response = $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/users/{$masterUser->username}");

    $response->assertStatus(403);
    // Admin cannot delete users due to policy restriction
});

it('allows master to edit other master users', function () {
    $master1 = User::factory()->create();
    $master1->assignRole('master');

    $master2 = User::factory()->create();
    $master2->assignRole('master');

    $updateData = [
        'name' => 'Updated Master Name',
    ];

    $response = $this->actingAs($master1, 'sanctum')
        ->putJson("/api/users/{$master2->username}", $updateData);

    $response->assertStatus(200);
    $response->assertJson([
        'message' => 'User updated successfully',
    ]);

    $master2->refresh();
    expect($master2->name)->toBe('Updated Master Name');
});

it('returns filtered roles based on user permission', function () {
    // Test admin user - should not see master role
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/users/roles');

    $response->assertStatus(200);
    $roles = $response->json('data');
    $roleNames = collect($roles)->pluck('name')->toArray();

    expect($roleNames)->not->toContain('master');
    expect($roleNames)->toContain('admin');
    expect($roleNames)->toContain('user');

    // Test master user - should see all roles including master
    $master = User::factory()->create();
    $master->assignRole('master');

    $response = $this->actingAs($master, 'sanctum')
        ->getJson('/api/users/roles');

    $response->assertStatus(200);
    $roles = $response->json('data');
    $roleNames = collect($roles)->pluck('name')->toArray();

    expect($roleNames)->toContain('master');
    expect($roleNames)->toContain('admin');
    expect($roleNames)->toContain('user');
});
