<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

// Create Task Tests
test('user can create a task', function () {
    $taskData = [
        'title' => 'Test Task Title',
        'description' => '<p>Test description</p>',
        'status' => 'todo',
        'priority' => 'high',
        'complexity' => 'medium',
        'visibility' => 'private',
    ];

    $response = $this->postJson('/api/tasks', $taskData);

    $response->assertSuccessful()
        ->assertJsonStructure([
            'message',
            'data' => [
                'id',
                'ulid',
                'title',
                'status',
                'priority',
                'complexity',
                'visibility',
            ],
        ]);

    $this->assertDatabaseHas('tasks', [
        'title' => 'Test Task Title',
        'status' => 'todo',
        'priority' => 'high',
        'complexity' => 'medium',
        'visibility' => 'private',
        'created_by' => $this->user->id,
    ]);
});

test('user can create task with shared users', function () {
    $sharedUser1 = User::factory()->create();
    $sharedUser2 = User::factory()->create();

    $taskData = [
        'title' => 'Shared Task',
        'description' => '<p>Description</p>',
        'status' => 'todo',
        'visibility' => 'shared',
        'shared_user_ids' => [$sharedUser1->id, $sharedUser2->id],
        'shared_roles' => [
            $sharedUser1->id => 'editor',
            $sharedUser2->id => 'viewer',
        ],
    ];

    $response = $this->postJson('/api/tasks', $taskData);

    $response->assertSuccessful();

    $task = Task::where('title', 'Shared Task')->first();

    expect($task->sharedUsers)->toHaveCount(2);

    // Check pivot data
    $editor = $task->sharedUsers()->wherePivot('role', 'editor')->first();
    expect($editor->id)->toBe($sharedUser1->id);

    $viewer = $task->sharedUsers()->wherePivot('role', 'viewer')->first();
    expect($viewer->id)->toBe($sharedUser2->id);
});

test('user can create task assigned to another user', function () {
    $assignee = User::factory()->create();

    $taskData = [
        'title' => 'Assigned Task',
        'description' => '<p>Description</p>',
        'status' => 'todo',
        'visibility' => 'private',
        'assignee_id' => $assignee->id,
    ];

    $response = $this->postJson('/api/tasks', $taskData);

    $response->assertSuccessful();

    $this->assertDatabaseHas('tasks', [
        'title' => 'Assigned Task',
        'assignee_id' => $assignee->id,
    ]);
});

test('user can create task linked to project', function () {
    $project = Project::factory()->create();

    $taskData = [
        'title' => 'Project Task',
        'description' => '<p>Description</p>',
        'status' => 'todo',
        'visibility' => 'private',
        'project_id' => $project->id,
    ];

    $response = $this->postJson('/api/tasks', $taskData);

    $response->assertSuccessful();

    $this->assertDatabaseHas('tasks', [
        'title' => 'Project Task',
        'project_id' => $project->id,
    ]);
});

test('task title is required', function () {
    $response = $this->postJson('/api/tasks', [
        'description' => '<p>Description</p>',
        'status' => 'todo',
        'visibility' => 'private',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['title']);
});

test('task visibility is required', function () {
    $response = $this->postJson('/api/tasks', [
        'title' => 'Test Task',
        'description' => '<p>Description</p>',
        'status' => 'todo',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['visibility']);
});

test('shared tasks require at least one shared user', function () {
    $response = $this->postJson('/api/tasks', [
        'title' => 'Shared Task',
        'description' => '<p>Description</p>',
        'status' => 'todo',
        'visibility' => 'shared',
        'shared_user_ids' => [],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['shared_user_ids']);
});

test('user can update their own task', function () {
    $task = Task::factory()->create([
        'created_by' => $this->user->id,
        'title' => 'Original Title',
        'status' => 'todo',
    ]);

    $response = $this->putJson("/api/tasks/{$task->ulid}", [
        'title' => 'Updated Title',
        'status' => 'in_progress',
    ]);

    $response->assertSuccessful();

    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        'title' => 'Updated Title',
        'status' => 'in_progress',
    ]);
});

test('user cannot update tasks they do not own', function () {
    $otherUser = User::factory()->create();
    $task = Task::factory()->create([
        'created_by' => $otherUser->id,
        'visibility' => 'private',
    ]);

    $response = $this->putJson("/api/tasks/{$task->ulid}", [
        'title' => 'Hacked Title',
    ]);

    $response->assertForbidden();
});

test('user can delete their own task', function () {
    $task = Task::factory()->create([
        'created_by' => $this->user->id,
    ]);

    $response = $this->deleteJson("/api/tasks/{$task->ulid}");

    $response->assertSuccessful();

    $this->assertSoftDeleted('tasks', [
        'id' => $task->id,
    ]);
});

test('user cannot delete tasks they do not own', function () {
    $otherUser = User::factory()->create();
    $task = Task::factory()->create([
        'created_by' => $otherUser->id,
        'visibility' => 'private',
    ]);

    $response = $this->deleteJson("/api/tasks/{$task->ulid}");

    $response->assertForbidden();
});

test('user can view their own tasks', function () {
    $task = Task::factory()->create([
        'created_by' => $this->user->id,
    ]);

    $response = $this->getJson("/api/tasks/{$task->ulid}");

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'id',
                'ulid',
                'title',
                'description',
                'status',
                'priority',
                'complexity',
                'visibility',
            ],
        ]);
});

test('user can view public tasks', function () {
    $otherUser = User::factory()->create();
    $task = Task::factory()->create([
        'created_by' => $otherUser->id,
        'visibility' => 'public',
    ]);

    $response = $this->getJson("/api/tasks/{$task->ulid}");

    $response->assertSuccessful();
});

test('user cannot view private tasks of other users', function () {
    $otherUser = User::factory()->create();
    $task = Task::factory()->create([
        'created_by' => $otherUser->id,
        'visibility' => 'private',
    ]);

    $response = $this->getJson("/api/tasks/{$task->ulid}");

    $response->assertForbidden();
});

test('task status auto-sets completed_at when marked as completed', function () {
    $task = Task::factory()->create([
        'created_by' => $this->user->id,
        'status' => 'todo',
        'completed_at' => null,
    ]);

    $task->update(['status' => 'completed']);
    $task->refresh();

    expect($task->completed_at)->not->toBeNull();
    expect($task->status)->toBe('completed');
});

test('task filters work correctly', function () {
    // Create various tasks assigned to current user
    Task::factory()->create(['created_by' => $this->user->id, 'assignee_id' => $this->user->id, 'status' => 'todo', 'priority' => 'high']);
    Task::factory()->create(['created_by' => $this->user->id, 'assignee_id' => $this->user->id, 'status' => 'in_progress', 'priority' => 'low']);
    Task::factory()->create(['created_by' => $this->user->id, 'assignee_id' => $this->user->id, 'status' => 'completed']);

    // Filter by status
    $response = $this->getJson('/api/tasks?filter_status=todo');
    $response->assertSuccessful();
    expect($response->json('meta.total'))->toBeGreaterThanOrEqual(1);

    // Filter by priority
    $response = $this->getJson('/api/tasks?filter_priority=high');
    $response->assertSuccessful();
    expect($response->json('meta.total'))->toBeGreaterThanOrEqual(1);
});
