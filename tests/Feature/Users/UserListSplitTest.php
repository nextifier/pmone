<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
});

function adminActor(): User
{
    $admin = User::factory()->create(['email_verified_at' => now(), 'status' => 'active']);
    $admin->assignRole('admin');

    return $admin;
}

it('returns only visitor (role=user) accounts when filtered by role, server-side paginated', function () {
    $admin = adminActor();

    $visitors = User::factory()->count(3)->create();
    foreach ($visitors as $v) {
        $v->assignRole('user');
    }
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $response = actingAs($admin)->getJson('/api/users?role=user&per_page=2')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 2);

    $roles = collect($response->json('data'))->pluck('roles')->flatten()->unique()->values()->all();
    expect($roles)->toBe(['user'])
        ->and($response->json('meta.total'))->toBe(3);
});

it('excludes multiple roles via comma-separated exclude_role (staff page)', function () {
    $admin = adminActor();

    $visitor = User::factory()->create();
    $visitor->assignRole('user');
    $exhibitor = User::factory()->create();
    $exhibitor->assignRole('exhibitor');
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $response = actingAs($admin)->getJson('/api/users?exclude_role=exhibitor,user&per_page=50')->assertOk();

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($staff->id)
        ->not->toContain($visitor->id)
        ->not->toContain($exhibitor->id);
});
