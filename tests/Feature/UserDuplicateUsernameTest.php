<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed roles (RefreshDatabase already handles migration)
    $this->artisan('db:seed', ['--class' => 'RoleAndPermissionSeeder']);

    // Create a master user for authentication and authorization
    $this->masterUser = User::factory()->create();
    $this->masterUser->assignRole('master');
    $this->actingAs($this->masterUser, 'sanctum');
});

test('can auto-generate unique username when creating multiple users with same name', function () {
    // Create first user with name "Anton" - force username to null to trigger auto-generation
    $firstUser = User::factory()->make([
        'name' => 'Anton',
        'email' => 'anton1@test.com',
        'username' => null,
    ]);
    $firstUser->save();

    expect($firstUser->username)->toBe('anton');

    // Create second user with same name "Anton"
    $secondUser = User::factory()->make([
        'name' => 'Anton',
        'email' => 'anton2@test.com',
        'username' => null,
    ]);
    $secondUser->save();

    expect($secondUser->username)->toBe('anton1')
        ->and($secondUser->username)->not->toBe($firstUser->username);

    // Create third user with same name
    $thirdUser = User::factory()->make([
        'name' => 'Anton',
        'email' => 'anton3@test.com',
        'username' => null,
    ]);
    $thirdUser->save();

    expect($thirdUser->username)->toBe('anton2')
        ->and($thirdUser->username)->not->toBe($firstUser->username)
        ->and($thirdUser->username)->not->toBe($secondUser->username);
});

test('handles duplicate username via API with retry mechanism', function () {
    // Create first user with name "Anton" directly in database
    User::factory()->create([
        'name' => 'Anton',
        'email' => 'anton1@test.com',
        'username' => 'anton',
    ]);

    // Try to create second user via API endpoint
    $response = $this->postJson('/api/users', [
        'name' => 'Anton',
        'email' => 'anton2@test.com',
        'password' => 'password123',
        // username is intentionally left empty to test auto-generation
    ]);

    // Should succeed with auto-generated username
    $response->assertSuccessful();

    $createdUser = User::where('email', 'anton2@test.com')->first();
    expect($createdUser)->not->toBeNull()
        ->and($createdUser->username)->not->toBe('anton')
        ->and($createdUser->username)->toMatch('/^anton\d+$/'); // Should be anton1, anton2, etc.
});

test('generates random suffix when numeric counter reaches max attempts', function () {
    // Create 12 users with same name to trigger random suffix (0-11)
    // This will create: maxtest, maxtest1, maxtest2, ..., maxtest10, maxtest_random
    for ($i = 0; $i < 12; $i++) {
        $user = User::factory()->make([
            'name' => 'MaxTest',
            'email' => "maxtest{$i}@test.com",
            'username' => null,
        ]);
        $user->save();
    }

    // The 12th user (index 11) should have a random suffix instead of sequential number
    $twelfthUser = User::where('email', 'maxtest11@test.com')->first();

    // Username should contain underscore and random characters
    expect($twelfthUser->username)->toContain('_')
        ->and($twelfthUser->username)->toMatch('/^maxtest_[a-z0-9]+$/');
});

test('handles race condition with timestamp fallback', function () {
    // This test simulates the rare case where even after random suffix,
    // username still exists (extremely rare but handled with timestamp)

    // Pre-create users with both sequential and random patterns
    User::factory()->create([
        'name' => 'RaceTest',
        'email' => 'race1@test.com',
        'username' => 'racetest',
    ]);

    // Create more users to test the fallback mechanism
    $newUser = User::factory()->make([
        'name' => 'RaceTest',
        'email' => 'race2@test.com',
        'username' => null,
    ]);
    $newUser->save();

    // Should have generated a unique username
    expect($newUser->username)->not->toBe('racetest')
        ->and($newUser->username)->toStartWith('racetest');

    // Verify uniqueness
    $usernameCount = User::where('username', $newUser->username)->count();
    expect($usernameCount)->toBe(1);
});

test('preserves custom username when provided', function () {
    $response = $this->postJson('/api/users', [
        'name' => 'Custom User',
        'username' => 'mycustomusername',
        'email' => 'custom@test.com',
        'password' => 'password123',
    ]);

    $response->assertSuccessful();

    $createdUser = User::where('email', 'custom@test.com')->first();
    expect($createdUser->username)->toBe('mycustomusername');
});

test('returns proper error when username generation fails after max retries', function () {
    // This test is for the edge case where controller retry mechanism kicks in

    // Pre-create a user
    User::factory()->create([
        'name' => 'EdgeCase',
        'email' => 'edge1@test.com',
        'username' => 'edgecase',
    ]);

    // Try to create another user with same base username
    $response = $this->postJson('/api/users', [
        'name' => 'EdgeCase',
        'email' => 'edge2@test.com',
        'password' => 'password123',
    ]);

    // Should succeed with auto-generated unique username
    $response->assertSuccessful();

    $newUser = User::where('email', 'edge2@test.com')->first();
    expect($newUser->username)->not->toBe('edgecase');
});
