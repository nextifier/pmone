<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can update user profile', function () {
    $user = User::factory()->create([
        'name' => 'Old Name',
        'username' => 'oldusername',
        'email' => 'old@example.com',
        'phone' => '123456789',
        'gender' => 'male',
        'bio' => 'Old bio',
        'visibility' => 'private',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->putJson('/api/user/profile/update', [
            'name' => 'New Name',
            'username' => 'newusername',
            'email' => 'new@example.com',
            'phone' => '987654321',
            'birth_date' => '1990-01-01',
            'gender' => 'female',
            'bio' => 'New bio description',
            'visibility' => 'public',
        ]);

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'id',
                'name',
                'username',
                'email',
                'phone',
                'birth_date',
                'gender',
                'bio',
                'visibility',
            ],
        ]);

    $user->refresh();

    expect($user->name)->toBe('New Name');
    expect($user->username)->toBe('newusername');
    expect($user->email)->toBe('new@example.com');
    expect($user->phone)->toBe('987654321');
    expect($user->birth_date->format('Y-m-d'))->toBe('1990-01-01');
    expect($user->gender)->toBe('female');
    expect($user->bio)->toBe('New bio description');
    expect($user->visibility)->toBe('public');
});

test('validates required fields when updating profile', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->putJson('/api/user/profile/update', [
            'name' => '',
            'username' => '',
            'email' => 'invalid-email',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'username', 'email']);
});

test('validates unique username and email when updating profile', function () {
    $user1 = User::factory()->create([
        'username' => 'user1',
        'email' => 'user1@example.com',
        'email_verified_at' => now(),
    ]);

    $user2 = User::factory()->create([
        'username' => 'user2',
        'email' => 'user2@example.com',
        'email_verified_at' => now(),
    ]);

    // Try to update user2 with user1's username and email
    $response = $this->actingAs($user2, 'sanctum')
        ->putJson('/api/user/profile', [
            'username' => 'user1',
            'email' => 'user1@example.com',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['username', 'email']);
});

test('allows user to keep their own username and email when updating', function () {
    $user = User::factory()->create([
        'name' => 'Test User',
        'username' => 'testuser',
        'email' => 'test@example.com',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->putJson('/api/user/profile/update', [
            'name' => 'Updated Name',
            'username' => 'testuser', // Same username
            'email' => 'test@example.com', // Same email
            'bio' => 'Updated bio',
        ]);

    $response->assertOk();

    $user->refresh();
    expect($user->name)->toBe('Updated Name');
    expect($user->username)->toBe('testuser');
    expect($user->email)->toBe('test@example.com');
    expect($user->bio)->toBe('Updated bio');
});
