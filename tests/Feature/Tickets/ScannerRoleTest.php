<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('creates the scanner role with scan and read permissions', function () {
    $scanner = Role::where('name', 'scanner')->first();

    expect($scanner)->not->toBeNull();

    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('scanner');

    expect($user->can('scan.check_in'))->toBeTrue()
        ->and($user->can('scan.exhibitor_lead'))->toBeTrue()
        ->and($user->can('tickets.read'))->toBeTrue()
        ->and($user->can('attendees.update'))->toBeTrue();
});

it('does not grant the scanner write access to tickets', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('scanner');

    expect($user->can('tickets.create'))->toBeFalse()
        ->and($user->can('tickets.update'))->toBeFalse()
        ->and($user->can('tickets.delete'))->toBeFalse()
        ->and($user->can('event_days.create'))->toBeFalse();
});
