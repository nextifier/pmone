<?php

use App\Models\Announcement;
use App\Models\AnnouncementUserDismissal;
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

it('filters by status', function () {
    Announcement::factory()->create(['title' => 'Published one', 'status' => 'published']);
    Announcement::factory()->draft()->create(['title' => 'Draft one']);
    Announcement::factory()->archived()->create(['title' => 'Archived one']);

    $response = $this->getJson('/api/announcements?filter_status=published');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'published');
});

it('filters by type', function () {
    Announcement::factory()->create(['title' => 'Info one', 'type' => 'info']);
    Announcement::factory()->create(['title' => 'Warning one', 'type' => 'warning']);

    $response = $this->getJson('/api/announcements?filter_type=warning');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'warning');
});

it('filters by search on title', function () {
    Announcement::factory()->create(['title' => 'Maintenance window']);
    Announcement::factory()->create(['title' => 'Welcome']);

    $response = $this->getJson('/api/announcements?filter_search=Maintenance');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Maintenance window');
});

it('sorts by order_column descending', function () {
    Announcement::factory()->create(['title' => 'First', 'order_column' => 0]);
    Announcement::factory()->create(['title' => 'Second', 'order_column' => 1]);
    Announcement::factory()->create(['title' => 'Third', 'order_column' => 2]);

    $response = $this->getJson('/api/announcements?sort_by=order_column&sort_dir=desc');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.order_column', 2)
        ->assertJsonPath('data.1.order_column', 1)
        ->assertJsonPath('data.2.order_column', 0);
});

it('sorts by order_column ascending by default', function () {
    Announcement::factory()->create(['title' => 'First', 'order_column' => 0]);
    Announcement::factory()->create(['title' => 'Second', 'order_column' => 1]);
    Announcement::factory()->create(['title' => 'Third', 'order_column' => 2]);

    $response = $this->getJson('/api/announcements');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.order_column', 0)
        ->assertJsonPath('data.1.order_column', 1)
        ->assertJsonPath('data.2.order_column', 2);
});

it('includes dismissals_count for each announcement', function () {
    $announcement = Announcement::factory()->create();

    $userA = User::factory()->create(['email_verified_at' => now()]);
    $userB = User::factory()->create(['email_verified_at' => now()]);

    AnnouncementUserDismissal::create([
        'announcement_id' => $announcement->id,
        'user_id' => $userA->id,
        'dismissed_at' => now(),
    ]);
    AnnouncementUserDismissal::create([
        'announcement_id' => $announcement->id,
        'user_id' => $userB->id,
        'dismissed_at' => now(),
    ]);

    $response = $this->getJson('/api/announcements');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.dismissals_count', 2);
});

it('returns all rows without pagination meta when client_only is true', function () {
    Announcement::factory()->count(3)->create();

    $response = $this->getJson('/api/announcements?client_only=true');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.last_page', 1)
        ->assertJsonPath('meta.per_page', 3);
});
