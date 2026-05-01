<?php

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['announcements.create', 'announcements.read', 'announcements.update', 'announcements.delete'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    $masterRole = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $masterRole->syncPermissions(Permission::all());

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);
});

it('lists announcements', function () {
    Announcement::factory()->count(3)->create();

    $response = $this->getJson('/api/announcements');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

it('creates an announcement', function () {
    $payload = [
        'title' => 'Welcome to v2',
        'description' => 'Lots of new features.',
        'icon' => 'hugeicons:notification-02',
        'type' => 'info',
        'status' => 'published',
        'is_global' => true,
        'cta_actions' => [
            ['label' => 'Read more', 'url' => '/help', 'style' => 'button-primary', 'icon' => null],
        ],
    ];

    $response = $this->postJson('/api/announcements', $payload);

    $response->assertSuccessful()
        ->assertJsonPath('data.title', 'Welcome to v2');

    $this->assertDatabaseHas('announcements', [
        'title' => 'Welcome to v2',
        'type' => 'info',
        'status' => 'published',
        'is_global' => true,
    ]);
});

it('requires title when creating', function () {
    $response = $this->postJson('/api/announcements', ['type' => 'info']);

    $response->assertStatus(422)->assertJsonValidationErrors(['title']);
});

it('rejects invalid type enum', function () {
    $response = $this->postJson('/api/announcements', [
        'title' => 'X',
        'type' => 'critical',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['type']);
});

it('rejects end_time before start_time', function () {
    $response = $this->postJson('/api/announcements', [
        'title' => 'X',
        'start_time' => '2026-06-01 10:00:00',
        'end_time' => '2026-05-01 10:00:00',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['end_time']);
});

it('updates an announcement', function () {
    $announcement = Announcement::factory()->create(['title' => 'Old']);

    $response = $this->putJson("/api/announcements/{$announcement->id}", [
        'title' => 'New',
        'status' => 'archived',
    ]);

    $response->assertSuccessful();
    expect($announcement->fresh()->title)->toBe('New');
    expect($announcement->fresh()->status)->toBe('archived');
});

it('soft-deletes an announcement', function () {
    $announcement = Announcement::factory()->create();

    $response = $this->deleteJson("/api/announcements/{$announcement->id}");

    $response->assertSuccessful();
    $this->assertSoftDeleted('announcements', ['id' => $announcement->id]);
});

it('restores a trashed announcement', function () {
    $announcement = Announcement::factory()->create();
    $announcement->delete();

    $response = $this->postJson("/api/announcements/trash/{$announcement->id}/restore");

    $response->assertSuccessful();
    expect($announcement->fresh()->trashed())->toBeFalse();
});

it('blocks users without permission from creating', function () {
    $regular = User::factory()->create(['email_verified_at' => now()]);
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $regular->assignRole('user');
    $this->actingAs($regular);

    $response = $this->postJson('/api/announcements', ['title' => 'X']);

    $response->assertForbidden();
});
