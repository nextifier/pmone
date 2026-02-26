<?php

use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $permissions = ['tasks.create', 'tasks.read', 'tasks.update', 'tasks.delete'];
    foreach ($permissions as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    $masterRole = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $masterRole->syncPermissions(Permission::all());
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

    $this->user = User::factory()->create();
    $this->user->assignRole('master');
    $this->actingAs($this->user);
});

// ============================================================
// INDEX
// ============================================================

it('lists user notifications', function () {
    // Create some notifications
    $this->user->notify(new TaskAssignedNotification(
        Task::factory()->create(),
        User::factory()->create()
    ));

    $response = $this->getJson('/api/notifications');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ])
        ->assertJsonPath('meta.total', 1);
});

it('filters unread notifications only', function () {
    // Create 2 notifications
    $this->user->notify(new TaskAssignedNotification(
        Task::factory()->create(),
        User::factory()->create()
    ));
    $this->user->notify(new TaskAssignedNotification(
        Task::factory()->create(),
        User::factory()->create()
    ));

    // Mark first as read
    $this->user->notifications()->first()->markAsRead();

    // Filter unread should return 1
    $response = $this->getJson('/api/notifications?filter=unread');

    $response->assertSuccessful()
        ->assertJsonPath('meta.total', 1);

    // All should return 2
    $response = $this->getJson('/api/notifications');

    $response->assertSuccessful()
        ->assertJsonPath('meta.total', 2);
});

it('returns empty list when no notifications', function () {
    $response = $this->getJson('/api/notifications');

    $response->assertSuccessful()
        ->assertJsonPath('meta.total', 0);
});

// ============================================================
// UNREAD COUNT
// ============================================================

it('returns unread count', function () {
    $this->user->notify(new TaskAssignedNotification(
        Task::factory()->create(),
        User::factory()->create()
    ));
    $this->user->notify(new TaskAssignedNotification(
        Task::factory()->create(),
        User::factory()->create()
    ));

    $response = $this->getJson('/api/notifications/unread-count');

    $response->assertSuccessful()
        ->assertJsonPath('data.unread_count', 2);
});

it('returns zero when all read', function () {
    $response = $this->getJson('/api/notifications/unread-count');

    $response->assertSuccessful()
        ->assertJsonPath('data.unread_count', 0);
});

// ============================================================
// MARK AS READ
// ============================================================

it('marks a single notification as read', function () {
    $this->user->notify(new TaskAssignedNotification(
        Task::factory()->create(),
        User::factory()->create()
    ));

    $notification = $this->user->notifications()->first();

    $response = $this->postJson("/api/notifications/{$notification->id}/mark-read");

    $response->assertSuccessful();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('returns 404 for other user notification', function () {
    $otherUser = User::factory()->create();
    $otherUser->notify(new TaskAssignedNotification(
        Task::factory()->create(),
        User::factory()->create()
    ));

    $notification = $otherUser->notifications()->first();

    $response = $this->postJson("/api/notifications/{$notification->id}/mark-read");

    $response->assertNotFound();
});

// ============================================================
// MARK ALL AS READ
// ============================================================

it('marks all notifications as read', function () {
    $this->user->notify(new TaskAssignedNotification(
        Task::factory()->create(),
        User::factory()->create()
    ));
    $this->user->notify(new TaskAssignedNotification(
        Task::factory()->create(),
        User::factory()->create()
    ));

    expect($this->user->unreadNotifications()->count())->toBe(2);

    $response = $this->postJson('/api/notifications/mark-all-read');

    $response->assertSuccessful();

    expect($this->user->unreadNotifications()->count())->toBe(0);
});

// ============================================================
// DISPATCH: TaskAssignedNotification
// ============================================================

it('dispatches notification when task assigned to another user', function () {
    $assignee = User::factory()->create();

    $response = $this->postJson('/api/tasks', [
        'title' => 'Test task',
        'assignee_id' => $assignee->id,
        'visibility' => 'public',
    ]);

    $response->assertCreated();

    expect($assignee->notifications()->count())->toBe(1);
    expect($assignee->notifications()->first()->data['title'])->toBe('New task assigned');
});

it('does not dispatch notification on self-assign', function () {
    $response = $this->postJson('/api/tasks', [
        'title' => 'Self assigned task',
        'assignee_id' => $this->user->id,
        'visibility' => 'public',
    ]);

    $response->assertCreated();

    expect($this->user->notifications()->count())->toBe(0);
});

it('dispatches notification when task reassigned to different user', function () {
    $originalAssignee = User::factory()->create();
    $newAssignee = User::factory()->create();

    $task = Task::factory()->create([
        'assignee_id' => $originalAssignee->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->putJson("/api/tasks/{$task->ulid}", [
        'title' => $task->title,
        'assignee_id' => $newAssignee->id,
    ]);

    $response->assertSuccessful();

    expect($newAssignee->notifications()->count())->toBe(1);
    expect($originalAssignee->notifications()->count())->toBe(0);
});

// ============================================================
// AUTH
// ============================================================

it('requires authentication for notification endpoints', function () {
    // Logout
    app('auth')->forgetGuards();

    $this->getJson('/api/notifications')->assertUnauthorized();
    $this->getJson('/api/notifications/unread-count')->assertUnauthorized();
    $this->postJson('/api/notifications/mark-all-read')->assertUnauthorized();
    $this->postJson('/api/notifications/fake-id/mark-read')->assertUnauthorized();
});
