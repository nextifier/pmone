<?php

use App\Models\Announcement;
use App\Models\AnnouncementUserDismissal;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['announcements.create', 'announcements.read', 'announcements.update', 'announcements.delete'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    foreach (['master', 'admin', 'staff', 'writer', 'user', 'marketing'] as $name) {
        Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('user');
    $this->actingAs($this->user);
});

it('returns global announcements to any authenticated user', function () {
    Announcement::factory()->create(['is_global' => true, 'status' => 'published']);

    $response = $this->getJson('/api/dashboard/announcements');

    $response->assertSuccessful()->assertJsonCount(1, 'data');
});

it('hides draft announcements from public endpoint', function () {
    Announcement::factory()->create(['is_global' => true, 'status' => 'draft']);

    $response = $this->getJson('/api/dashboard/announcements');

    $response->assertSuccessful()->assertJsonCount(0, 'data');
});

it('hides announcements outside the active window', function () {
    Announcement::factory()->create([
        'is_global' => true,
        'status' => 'published',
        'start_time' => now()->addDays(2),
    ]);
    Announcement::factory()->create([
        'is_global' => true,
        'status' => 'published',
        'end_time' => now()->subDay(),
    ]);

    $response = $this->getJson('/api/dashboard/announcements');

    $response->assertSuccessful()->assertJsonCount(0, 'data');
});

it('filters by target_roles', function () {
    Announcement::factory()->create([
        'is_global' => false,
        'status' => 'published',
        'target_roles' => ['marketing'],
    ]);

    $response = $this->getJson('/api/dashboard/announcements');
    $response->assertJsonCount(0, 'data');

    $this->user->assignRole('marketing');

    $response = $this->getJson('/api/dashboard/announcements');
    $response->assertJsonCount(1, 'data');
});

it('shows announcement targeted to specific user', function () {
    $announcement = Announcement::factory()->create([
        'is_global' => false,
        'status' => 'published',
    ]);
    $announcement->users()->attach($this->user->id);

    $response = $this->getJson('/api/dashboard/announcements');

    $response->assertJsonCount(1, 'data');
});

it('shows announcement targeted to user project', function () {
    $project = Project::factory()->create();
    $this->user->projects()->attach($project->id);

    $announcement = Announcement::factory()->create([
        'is_global' => false,
        'status' => 'published',
    ]);
    $announcement->projects()->attach($project->id);

    $response = $this->getJson('/api/dashboard/announcements');

    $response->assertJsonCount(1, 'data');
});

it('hides announcement after user dismisses it', function () {
    $announcement = Announcement::factory()->create(['is_global' => true, 'status' => 'published']);

    $this->postJson("/api/dashboard/announcements/{$announcement->id}/dismiss")
        ->assertSuccessful();

    $this->assertDatabaseHas('announcement_user_dismissals', [
        'announcement_id' => $announcement->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->getJson('/api/dashboard/announcements');
    $response->assertJsonCount(0, 'data');
});

it('dismiss is idempotent', function () {
    $announcement = Announcement::factory()->create(['is_global' => true, 'status' => 'published']);

    $this->postJson("/api/dashboard/announcements/{$announcement->id}/dismiss")->assertSuccessful();
    $this->postJson("/api/dashboard/announcements/{$announcement->id}/dismiss")->assertSuccessful();

    expect(AnnouncementUserDismissal::count())->toBe(1);
});
