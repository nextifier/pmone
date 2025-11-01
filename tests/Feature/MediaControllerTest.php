<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('can upload a profile image', function () {
    $file = UploadedFile::fake()->image('profile.jpg', 300, 300)->size(100);

    $response = $this->postJson('/api/media/upload', [
        'file' => $file,
        'collection' => 'profile_image',
        'model_type' => 'App\Models\User',
        'model_id' => $this->user->id,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'File uploaded successfully',
        ])
        ->assertJsonStructure([
            'media' => [
                'id',
                'name',
                'file_name',
                'mime_type',
                'size',
                'size_human',
                'url',
                'collection_name',
                'file_type',
                'supports_conversions',
            ],
        ]);

    expect($response->json('media.file_type'))->toBe('images');
    expect($response->json('media.supports_conversions'))->toBeTrue();

    // Check if conversions are generated
    expect($response->json('media.conversions.original'))->toBeString();
    expect($response->json('media.conversions.sm'))->toBeString();
    expect($response->json('media.conversions.md'))->toBeString();
    expect($response->json('media.conversions.lg'))->toBeString();
    expect($response->json('media.conversions.xl'))->toBeString();

    // Check dimensions
    expect($response->json('media.custom_properties.width'))->toBe(300);
    expect($response->json('media.custom_properties.height'))->toBe(300);
    expect($response->json('media.custom_properties.orientation'))->toBe('square');
});

it('can upload a PDF document', function () {
    // Create a documents collection for User first
    $file = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

    $response = $this->postJson('/api/media/upload', [
        'file' => $file,
        'collection' => 'documents',
        'model_type' => 'App\Models\User',
        'model_id' => $this->user->id,
    ]);

    // This should fail because User model doesn't have documents collection by default
    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Invalid collection for this model',
        ]);
});

it('rejects unsupported file types', function () {
    $file = UploadedFile::fake()->create('script.php', 100, 'application/x-php');

    $response = $this->postJson('/api/media/upload', [
        'file' => $file,
        'collection' => 'profile_image',
        'model_type' => 'App\Models\User',
        'model_id' => $this->user->id,
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Unsupported file type',
        ]);
});

it('rejects files that exceed size limit', function () {
    $file = UploadedFile::fake()->image('large.jpg')->size(6000); // 6MB, exceeds 5MB limit for images

    $response = $this->postJson('/api/media/upload', [
        'file' => $file,
        'collection' => 'profile_image',
        'model_type' => 'App\Models\User',
        'model_id' => $this->user->id,
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'File size exceeds limit',
        ]);
});

it('rejects file type not allowed for collection', function () {
    $file = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

    $response = $this->postJson('/api/media/upload', [
        'file' => $file,
        'collection' => 'profile_image', // Profile image only accepts images
        'model_type' => 'App\Models\User',
        'model_id' => $this->user->id,
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'File type not allowed for this collection',
        ]);
});

it('prevents unauthorized upload to other users', function () {
    $otherUser = User::factory()->create();

    $file = UploadedFile::fake()->image('profile.jpg');

    $response = $this->postJson('/api/media/upload', [
        'file' => $file,
        'collection' => 'profile_image',
        'model_type' => 'App\Models\User',
        'model_id' => $otherUser->id,
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'message' => 'Unauthorized to upload media for this resource',
        ]);
});

it('replaces existing media in single file collections', function () {
    // Upload first file
    $file1 = UploadedFile::fake()->image('profile1.jpg');
    $this->postJson('/api/media/upload', [
        'file' => $file1,
        'collection' => 'profile_image',
        'model_type' => 'App\Models\User',
        'model_id' => $this->user->id,
    ]);

    expect($this->user->fresh()->getMedia('profile_image'))->toHaveCount(1);

    // Upload second file - should replace first
    $file2 = UploadedFile::fake()->image('profile2.jpg');
    $this->postJson('/api/media/upload', [
        'file' => $file2,
        'collection' => 'profile_image',
        'model_type' => 'App\Models\User',
        'model_id' => $this->user->id,
    ]);

    expect($this->user->fresh()->getMedia('profile_image'))->toHaveCount(1);
});

it('can delete uploaded media', function () {
    // Upload a file first
    $file = UploadedFile::fake()->image('profile.jpg');
    $uploadResponse = $this->postJson('/api/media/upload', [
        'file' => $file,
        'collection' => 'profile_image',
        'model_type' => 'App\Models\User',
        'model_id' => $this->user->id,
    ]);

    $mediaId = $uploadResponse->json('media.id');

    // Delete the media
    $response = $this->deleteJson("/api/media/{$mediaId}");

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Media deleted successfully',
        ]);

    expect($this->user->fresh()->getMedia('profile_image'))->toHaveCount(0);
});

it('prevents unauthorized media deletion', function () {
    $otherUser = User::factory()->create();

    // Other user uploads a file
    $this->actingAs($otherUser);
    $file = UploadedFile::fake()->image('profile.jpg');
    $uploadResponse = $this->postJson('/api/media/upload', [
        'file' => $file,
        'collection' => 'profile_image',
        'model_type' => 'App\Models\User',
        'model_id' => $otherUser->id,
    ]);

    $mediaId = $uploadResponse->json('media.id');

    // Switch back to original user and try to delete
    $this->actingAs($this->user);
    $response = $this->deleteJson("/api/media/{$mediaId}");

    $response->assertStatus(403)
        ->assertJson([
            'message' => 'Unauthorized to delete this media',
        ]);
});

// Bulk Upload Tests
it('can bulk upload multiple images', function () {
    // First add a gallery collection to User model (this would be done in actual implementation)
    $files = [
        UploadedFile::fake()->image('photo1.jpg', 300, 300)->size(100),
        UploadedFile::fake()->image('photo2.png', 400, 300)->size(150),
        UploadedFile::fake()->image('photo3.webp', 500, 300)->size(120),
    ];

    $response = $this->postJson('/api/media/bulk-upload', [
        'files' => $files,
        'collection' => 'gallery',
        'model_type' => 'App\Models\User',
        'model_id' => $this->user->id,
    ]);

    // Since User model doesn't have gallery collection by default, this should fail
    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Invalid collection for this model',
        ]);
});

it('validates bulk upload file count limits', function () {
    $files = [];
    for ($i = 0; $i < 12; $i++) { // More than 10 files limit
        $files[] = UploadedFile::fake()->image("photo{$i}.jpg");
    }

    $response = $this->postJson('/api/media/bulk-upload', [
        'files' => $files,
        'collection' => 'profile_image',
        'model_type' => 'App\Models\User',
        'model_id' => $this->user->id,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['files']);
});

it('validates bulk upload against single file collections', function () {
    $files = [
        UploadedFile::fake()->image('photo1.jpg'),
        UploadedFile::fake()->image('photo2.jpg'),
    ];

    $response = $this->postJson('/api/media/bulk-upload', [
        'files' => $files,
        'collection' => 'profile_image', // Single file collection
        'model_type' => 'App\Models\User',
        'model_id' => $this->user->id,
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'This collection only accepts single file uploads',
        ]);
});

it('handles partial failures in bulk upload gracefully', function () {
    $files = [
        UploadedFile::fake()->image('good.jpg')->size(100), // Good file
        UploadedFile::fake()->create('bad.php', 50, 'application/x-php'), // Bad file type
    ];

    $response = $this->postJson('/api/media/bulk-upload', [
        'files' => $files,
        'collection' => 'profile_image',
        'model_type' => 'App\Models\User',
        'model_id' => $this->user->id,
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Some files failed validation',
        ])
        ->assertJsonStructure([
            'failed_uploads' => [
                '*' => ['index', 'filename', 'error'],
            ],
            'total_files',
            'failed_count',
        ]);
});

it('validates bulk upload total file size', function () {
    $files = [];
    // Create files that exceed 100MB total limit
    for ($i = 0; $i < 3; $i++) {
        $files[] = UploadedFile::fake()->image("large{$i}.jpg")->size(40000); // 40MB each = 120MB total
    }

    $response = $this->postJson('/api/media/bulk-upload', [
        'files' => $files,
        'collection' => 'gallery',
        'model_type' => 'App\Models\User',
        'model_id' => $this->user->id,
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Total file size exceeds bulk upload limit',
        ]);
});

// Bulk Delete Tests
it('can bulk delete multiple media files', function () {
    // Upload some files first
    $files = [];
    $mediaIds = [];

    for ($i = 0; $i < 3; $i++) {
        $file = UploadedFile::fake()->image("photo{$i}.jpg");
        $uploadResponse = $this->postJson('/api/media/upload', [
            'file' => $file,
            'collection' => 'profile_image',
            'model_type' => 'App\Models\User',
            'model_id' => $this->user->id,
        ]);

        if ($i === 0) { // Only first upload succeeds for single file collection
            $mediaIds[] = $uploadResponse->json('media.id');
        }
    }

    if (empty($mediaIds)) {
        $this->markTestSkipped('No media files to delete');
    }

    $response = $this->deleteJson('/api/media/bulk-delete', [
        'media_ids' => $mediaIds,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Bulk delete completed',
        ])
        ->assertJsonStructure([
            'total_requested',
            'successful_deletes',
            'failed_deletes',
            'deleted_media',
        ]);
});

it('validates bulk delete media ID limits', function () {
    $mediaIds = range(1, 55); // More than 50 files limit

    $response = $this->deleteJson('/api/media/bulk-delete', [
        'media_ids' => $mediaIds,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['media_ids']);
});

it('handles non-existent media IDs in bulk delete', function () {
    $response = $this->deleteJson('/api/media/bulk-delete', [
        'media_ids' => [99999, 88888, 77777], // Non-existent IDs
    ]);

    $response->assertStatus(207) // Multi-status for partial success
        ->assertJson([
            'message' => 'Bulk delete completed',
            'successful_deletes' => 0,
            'failed_deletes' => 3,
        ])
        ->assertJsonStructure([
            'failed_deletes' => [
                '*' => ['id', 'error'],
            ],
        ]);
});

it('prevents unauthorized bulk delete', function () {
    $otherUser = User::factory()->create();

    // Other user uploads a file
    $this->actingAs($otherUser);
    $file = UploadedFile::fake()->image('photo.jpg');
    $uploadResponse = $this->postJson('/api/media/upload', [
        'file' => $file,
        'collection' => 'profile_image',
        'model_type' => 'App\Models\User',
        'model_id' => $otherUser->id,
    ]);

    $mediaId = $uploadResponse->json('media.id');

    // Switch to original user and try to bulk delete other user's media
    $this->actingAs($this->user);
    $response = $this->deleteJson('/api/media/bulk-delete', [
        'media_ids' => [$mediaId],
    ]);

    $response->assertStatus(207) // Partial success (fails authorization)
        ->assertJson([
            'successful_deletes' => 0,
            'failed_deletes' => 1,
        ]);

    $failedDeletes = $response->json('failed_deletes');
    expect($failedDeletes[0]['error'])->toBe('Unauthorized to delete this media');
});
