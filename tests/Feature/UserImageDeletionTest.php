<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');

    // Seed roles (RefreshDatabase already handles migration)
    $this->artisan('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
});

test('can delete profile image with delete flag', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    // Add a profile image first
    $file = UploadedFile::fake()->image('profile.jpg');
    $user->addMedia($file)->toMediaCollection('profile_image');

    expect($user->getMedia('profile_image'))->toHaveCount(1);

    // Update user with delete flag
    $response = $this->actingAs($user, 'sanctum')
        ->putJson("/api/users/{$user->username}", [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'delete_profile_image' => true,
        ]);

    $response->assertOk();

    $user->refresh();
    expect($user->getMedia('profile_image'))->toHaveCount(0);
});

test('can delete cover image with delete flag', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    // Add a cover image first
    $file = UploadedFile::fake()->image('cover.jpg');
    $user->addMedia($file)->toMediaCollection('cover_image');

    expect($user->getMedia('cover_image'))->toHaveCount(1);

    // Update user with delete flag
    $response = $this->actingAs($user, 'sanctum')
        ->putJson("/api/users/{$user->username}", [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'delete_cover_image' => true,
        ]);

    $response->assertOk();

    $user->refresh();
    expect($user->getMedia('cover_image'))->toHaveCount(0);
});

test('keeps existing image when no delete flag is sent', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    // Add a profile image first
    $file = UploadedFile::fake()->image('profile.jpg');
    $user->addMedia($file)->toMediaCollection('profile_image');

    expect($user->getMedia('profile_image'))->toHaveCount(1);

    // Update user without delete flag
    $response = $this->actingAs($user, 'sanctum')
        ->putJson("/api/users/{$user->username}", [
            'name' => 'Updated Name',
            'username' => $user->username,
            'email' => $user->email,
        ]);

    $response->assertOk();

    $user->refresh();
    expect($user->getMedia('profile_image'))->toHaveCount(1);
});

test('can delete and upload new image in same request', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $user->assignRole('admin');

    // Add a profile image first
    $oldFile = UploadedFile::fake()->image('old-profile.jpg');
    $user->addMedia($oldFile)->toMediaCollection('profile_image');

    expect($user->getMedia('profile_image'))->toHaveCount(1);

    // Create temporary upload
    $newFile = UploadedFile::fake()->image('new-profile.jpg');
    $tmpId = 'tmp-'.uniqid();
    $tmpPath = "tmp/uploads/{$tmpId}";

    Storage::disk('local')->put("{$tmpPath}/{$newFile->getClientOriginalName()}", $newFile->getContent());
    Storage::disk('local')->put("{$tmpPath}/metadata.json", json_encode([
        'original_name' => $newFile->getClientOriginalName(),
        'mime_type' => $newFile->getMimeType(),
        'size' => $newFile->getSize(),
    ]));

    // Update user with new image (should replace old one)
    $response = $this->actingAs($user, 'sanctum')
        ->putJson("/api/users/{$user->username}", [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'tmp_profile_image' => $tmpId,
        ]);

    $response->assertOk();

    $user->refresh();
    expect($user->getMedia('profile_image'))->toHaveCount(1);
});
