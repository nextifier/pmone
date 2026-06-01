<?php

use App\Models\Announcement;
use App\Models\Event;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['announcements.create', 'announcements.read', 'announcements.update', 'announcements.delete'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    foreach (['master', 'admin', 'staff', 'writer', 'user', 'exhibitor'] as $name) {
        Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('user');
    $this->actingAs($this->user);
});

it('shows event-targeted announcement to a member of the events project', function () {
    $project = Project::factory()->create();
    $this->user->projects()->attach($project->id);

    $event = Event::factory()->create(['project_id' => $project->id]);

    $announcement = Announcement::factory()->create([
        'is_global' => false,
        'status' => 'published',
    ]);
    $announcement->events()->attach($event->id);

    $response = $this->getJson('/api/dashboard/announcements');

    $response->assertSuccessful()->assertJsonCount(1, 'data');
});

it('hides event-targeted announcement from a user not in the events project', function () {
    $project = Project::factory()->create();

    $event = Event::factory()->create(['project_id' => $project->id]);

    $announcement = Announcement::factory()->create([
        'is_global' => false,
        'status' => 'published',
    ]);
    $announcement->events()->attach($event->id);

    $response = $this->getJson('/api/dashboard/announcements');

    $response->assertSuccessful()->assertJsonCount(0, 'data');
});

it('hides archived announcements while showing a sibling published one', function () {
    Announcement::factory()->archived()->create(['is_global' => true]);
    Announcement::factory()->create(['is_global' => true, 'status' => 'published']);

    $response = $this->getJson('/api/dashboard/announcements');

    $response->assertSuccessful()->assertJsonCount(1, 'data');
});

it('matches any one of a users multiple roles against target_roles', function () {
    $this->user->assignRole('staff', 'writer');

    Announcement::factory()->targetingRoles(['writer'])->create(['status' => 'published']);

    $response = $this->getJson('/api/dashboard/announcements');
    $response->assertSuccessful()->assertJsonCount(1, 'data');
});

it('hides role-targeted announcement when the user holds none of the target roles', function () {
    $this->user->assignRole('staff', 'writer');

    Announcement::factory()->targetingRoles(['exhibitor'])->create(['status' => 'published']);

    $response = $this->getJson('/api/dashboard/announcements');
    $response->assertSuccessful()->assertJsonCount(0, 'data');
});

it('orders announcements by order_column ascending', function () {
    Announcement::factory()->create(['is_global' => true, 'status' => 'published', 'order_column' => 2]);
    Announcement::factory()->create(['is_global' => true, 'status' => 'published', 'order_column' => 0]);
    Announcement::factory()->create(['is_global' => true, 'status' => 'published', 'order_column' => 1]);

    $response = $this->getJson('/api/dashboard/announcements');

    $response->assertSuccessful()->assertJsonCount(3, 'data');

    $orderColumns = collect($response->json('data'))->pluck('order_column')->all();

    expect($orderColumns)->toBe([0, 1, 2]);
});

it('treats an end_time one second in the future as still active', function () {
    Announcement::factory()->create([
        'is_global' => true,
        'status' => 'published',
        'end_time' => now()->addSecond(),
    ]);

    $response = $this->getJson('/api/dashboard/announcements');

    $response->assertSuccessful()->assertJsonCount(1, 'data');
});

it('hides an announcement whose end_time is in the past', function () {
    Announcement::factory()->create([
        'is_global' => true,
        'status' => 'published',
        'end_time' => now()->subSecond(),
    ]);

    $response = $this->getJson('/api/dashboard/announcements');

    $response->assertSuccessful()->assertJsonCount(0, 'data');
});

it('returns 401 for an unauthenticated guest hitting the dashboard endpoint', function () {
    // The route is behind auth:sanctum, so a guest never reaches the
    // is_global-only branch of visibleTo(); that path is for non-HTTP callers.
    Auth::guard('web')->logout();
    app('auth')->forgetGuards();

    Announcement::factory()->create(['is_global' => true, 'status' => 'published']);

    $response = $this->getJson('/api/dashboard/announcements');

    $response->assertStatus(401);
});
