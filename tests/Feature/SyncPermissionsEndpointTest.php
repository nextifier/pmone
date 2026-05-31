<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
});

test('a master can sync permissions and recreate a missing one', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('master');

    Permission::where('name', 'admin.logs_clear')->delete();

    $this->actingAs($user)
        ->postJson(route('system.permissions.sync'))
        ->assertOk()
        ->assertJsonPath('message', 'Permissions synced successfully.');

    expect(Permission::where('name', 'admin.logs_clear')->exists())->toBeTrue();
    expect($user->fresh()->hasPermissionTo('admin.logs_clear'))->toBeTrue();
    expect(Activity::where('description', 'Synced permissions from config')->count())->toBe(1);
});

test('a user without admin.settings permission cannot sync permissions', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('user');

    $this->actingAs($user)
        ->postJson(route('system.permissions.sync'))
        ->assertForbidden();
});

test('a guest cannot sync permissions', function () {
    $this->postJson(route('system.permissions.sync'))
        ->assertUnauthorized();
});
