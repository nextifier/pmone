<?php

use App\Models\User;
use App\Models\UserNote;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
});

it('creates, lists and deletes internal notes with permission', function () {
    $admin = securityTestUser(['users.manage_notes']);
    $target = User::factory()->create();

    actingAs($admin)->postJson("/api/users/{$target->username}/notes", ['body' => 'Suspicious activity'])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Suspicious activity')
        ->assertJsonPath('data.author.id', $admin->id);

    actingAs($admin)->getJson("/api/users/{$target->username}/notes")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $note = UserNote::first();
    actingAs($admin)->deleteJson("/api/users/{$target->username}/notes/{$note->id}")->assertOk();
    expect(UserNote::count())->toBe(0);
});

it('forbids notes access without manage_notes permission', function () {
    $admin = securityTestUser();
    $target = User::factory()->create();

    actingAs($admin)->getJson("/api/users/{$target->username}/notes")->assertForbidden();
    actingAs($admin)->postJson("/api/users/{$target->username}/notes", ['body' => 'x'])->assertForbidden();
});

it('does not delete a note belonging to a different user', function () {
    $admin = securityTestUser(['users.manage_notes']);
    $target = User::factory()->create();
    $other = User::factory()->create();
    $note = UserNote::factory()->create(['user_id' => $other->id]);

    actingAs($admin)->deleteJson("/api/users/{$target->username}/notes/{$note->id}")->assertNotFound();
    expect(UserNote::find($note->id))->not->toBeNull();
});
