<?php

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $announcementPermissions = ['announcements.create', 'announcements.read', 'announcements.update', 'announcements.delete'];

    foreach ($announcementPermissions as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    foreach (['master', 'admin', 'staff', 'writer', 'user', 'exhibitor'] as $name) {
        Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    Role::findByName('master', 'web')->syncPermissions($announcementPermissions);
    Role::findByName('admin', 'web')->syncPermissions($announcementPermissions);
});

dataset('allowed roles', [
    'master' => ['master'],
    'admin' => ['admin'],
]);

dataset('forbidden roles', [
    'staff' => ['staff'],
    'writer' => ['writer'],
    'user' => ['user'],
    'exhibitor' => ['exhibitor'],
]);

it('allows the role to create an announcement', function (string $role) {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);
    $this->actingAs($user);

    $response = $this->postJson('/api/announcements', [
        'title' => 'Authorized announcement',
        'type' => 'info',
    ]);

    $response->assertSuccessful();
    $this->assertDatabaseHas('announcements', ['title' => 'Authorized announcement']);
})->with('allowed roles');

it('allows the role to update an announcement', function (string $role) {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);
    $this->actingAs($user);

    $announcement = Announcement::factory()->create(['title' => 'Old title']);

    $response = $this->putJson("/api/announcements/{$announcement->id}", [
        'title' => 'New title',
        'type' => 'warning',
    ]);

    $response->assertSuccessful();
    expect($announcement->fresh()->title)->toBe('New title');
})->with('allowed roles');

it('allows the role to delete an announcement', function (string $role) {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);
    $this->actingAs($user);

    $announcement = Announcement::factory()->create();

    $response = $this->deleteJson("/api/announcements/{$announcement->id}");

    $response->assertSuccessful();
    $this->assertSoftDeleted('announcements', ['id' => $announcement->id]);
})->with('allowed roles');

it('forbids the role from creating an announcement', function (string $role) {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);
    $this->actingAs($user);

    $response = $this->postJson('/api/announcements', [
        'title' => 'Unauthorized announcement',
        'type' => 'info',
    ]);

    $response->assertForbidden();
})->with('forbidden roles');

it('forbids the role from updating an announcement', function (string $role) {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);
    $this->actingAs($user);

    $announcement = Announcement::factory()->create(['title' => 'Old title']);

    $response = $this->putJson("/api/announcements/{$announcement->id}", [
        'title' => 'New title',
        'type' => 'warning',
    ]);

    $response->assertForbidden();
    expect($announcement->fresh()->title)->toBe('Old title');
})->with('forbidden roles');

it('forbids the role from deleting an announcement', function (string $role) {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);
    $this->actingAs($user);

    $announcement = Announcement::factory()->create();

    $response = $this->deleteJson("/api/announcements/{$announcement->id}");

    $response->assertForbidden();
    $this->assertDatabaseHas('announcements', ['id' => $announcement->id, 'deleted_at' => null]);
})->with('forbidden roles');

it('rejects unauthenticated create requests', function () {
    $response = $this->postJson('/api/announcements', [
        'title' => 'Anonymous announcement',
        'type' => 'info',
    ]);

    $response->assertUnauthorized();
});

it('rejects unauthenticated update requests', function () {
    $announcement = Announcement::factory()->create(['title' => 'Old title']);

    $response = $this->putJson("/api/announcements/{$announcement->id}", [
        'title' => 'New title',
        'type' => 'warning',
    ]);

    $response->assertUnauthorized();
});

it('rejects unauthenticated delete requests', function () {
    $announcement = Announcement::factory()->create();

    $response = $this->deleteJson("/api/announcements/{$announcement->id}");

    $response->assertUnauthorized();
});
