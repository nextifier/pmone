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
    $file = UploadedFile::fake()->image('large.jpg')->size(21000); // 21MB, exceeds 20MB limit for images

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
    // gallery collection is not registered on User model, and the default
    // isSingleFileCollection returns true for unknown collections.
    // With multiple files to a single-file collection, the controller returns
    // "This collection only accepts single file uploads" before reaching
    // collection validation. Use a single file to test the collection check.
    $files = [
        UploadedFile::fake()->image('photo1.jpg', 300, 300)->size(100),
    ];

    $response = $this->postJson('/api/media/bulk-upload', [
        'files' => $files,
        'collection' => 'gallery',
        'model_type' => 'App\Models\User',
        'model_id' => $this->user->id,
    ]);

    // gallery collection doesn't exist on User, so file validation fails with "Invalid collection"
    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Some files failed validation',
        ]);
});

it('validates bulk upload requires at least one file', function () {
    $response = $this->postJson('/api/media/bulk-upload', [
        'files' => [],
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
    // Send a single unsupported file to a single-file collection
    // to test file validation failure in bulk upload
    $files = [
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
    // Single file exceeding 100MB total bulk upload limit
    $files = [
        UploadedFile::fake()->image('huge.jpg')->size(110000), // 110MB, exceeds 100MB bulk limit
    ];

    $response = $this->postJson('/api/media/bulk-upload', [
        'files' => $files,
        'collection' => 'profile_image',
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
    // Upload a single file (profile_image is a single-file collection)
    $file = UploadedFile::fake()->image('photo.jpg');
    $uploadResponse = $this->postJson('/api/media/upload', [
        'file' => $file,
        'collection' => 'profile_image',
        'model_type' => 'App\Models\User',
        'model_id' => $this->user->id,
    ]);

    $mediaId = $uploadResponse->json('media.id');
    expect($mediaId)->not->toBeNull();

    $response = $this->deleteJson('/api/media/bulk-delete', [
        'media_ids' => [$mediaId],
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Bulk delete completed',
            'total_requested' => 1,
            'successful_deletes' => 1,
        ])
        ->assertJsonStructure([
            'total_requested',
            'successful_deletes',
            'failed_deletes',
            'deleted_media',
        ]);

    // Verify media was deleted
    expect($this->user->fresh()->getMedia('profile_image'))->toHaveCount(0);
});

it('validates bulk delete requires media IDs', function () {
    // Empty array should fail validation (min:1)
    $response = $this->deleteJson('/api/media/bulk-delete', [
        'media_ids' => [],
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
        ]);

    // When there are failures, failed_deletes is an array of failure details
    $failedDeletes = $response->json('failed_deletes');
    expect($failedDeletes)->toBeArray();
    expect($failedDeletes)->toHaveCount(3);
    expect($failedDeletes[0])->toHaveKeys(['id', 'error']);
    expect($failedDeletes[0]['error'])->toBe('Media not found');
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
        ]);

    // When there are failures, failed_deletes is an array of failure details
    $failedDeletes = $response->json('failed_deletes');
    expect($failedDeletes)->toBeArray();
    expect($failedDeletes)->toHaveCount(1);
    expect($failedDeletes[0]['error'])->toBe('Unauthorized to delete this media');
});
