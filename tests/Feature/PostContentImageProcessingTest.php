<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('processes content images with relative URL format', function () {
    // Create a temporary media file
    $folder = 'tmp-media-'.uniqid('', true);
    $filename = 'test-image.jpg';
    $tempFilePath = "tmp/uploads/{$folder}/{$filename}";
    $metadataPath = "tmp/uploads/{$folder}/metadata.json";

    // Create fake image file
    $image = UploadedFile::fake()->image($filename);
    Storage::disk('local')->put($tempFilePath, file_get_contents($image->getPathname()));
    Storage::disk('local')->put($metadataPath, json_encode([
        'original_name' => $filename,
        'mime_type' => 'image/jpeg',
        'size' => $image->getSize(),
        'collection' => 'content_images',
        'uploaded_at' => now()->toISOString(),
    ]));

    // Create post with relative URL format
    $response = $this->postJson('/api/posts', [
        'title' => 'Test Post with Image',
        'content' => '<p>Hello</p><img class="post-content-image" src="/api/tmp-media/'.$folder.'"><p></p>',
        'status' => 'draft',
    ]);

    $response->assertSuccessful();

    $post = Post::find($response->json('data.id'));

    // Content should be updated with permanent URL
    expect($post->content)->not->toContain('/api/tmp-media/');
    expect($post->content)->toContain('/storage/');

    // Media record should exist
    expect($post->getMedia('content_images'))->toHaveCount(1);
});

it('processes content images with absolute URL format', function () {
    // Create a temporary media file
    $folder = 'tmp-media-'.uniqid('', true);
    $filename = 'test-image.jpg';
    $tempFilePath = "tmp/uploads/{$folder}/{$filename}";
    $metadataPath = "tmp/uploads/{$folder}/metadata.json";

    // Create fake image file
    $image = UploadedFile::fake()->image($filename);
    Storage::disk('local')->put($tempFilePath, file_get_contents($image->getPathname()));
    Storage::disk('local')->put($metadataPath, json_encode([
        'original_name' => $filename,
        'mime_type' => 'image/jpeg',
        'size' => $image->getSize(),
        'collection' => 'content_images',
        'uploaded_at' => now()->toISOString(),
    ]));

    // Create post with absolute URL format (like http://localhost:8000/api/tmp-media/...)
    $response = $this->postJson('/api/posts', [
        'title' => 'Test Post with Absolute URL Image',
        'content' => '<p>Hello</p><img class="post-content-image" src="http://localhost:8000/api/tmp-media/'.$folder.'"><p></p>',
        'status' => 'draft',
    ]);

    $response->assertSuccessful();

    $post = Post::find($response->json('data.id'));

    // Content should be updated with permanent URL
    expect($post->content)->not->toContain('/api/tmp-media/');
    expect($post->content)->not->toContain('http://localhost:8000');
    expect($post->content)->toContain('/storage/');

    // Media record should exist
    expect($post->getMedia('content_images'))->toHaveCount(1);
});

it('processes content images with https absolute URL format', function () {
    // Create a temporary media file
    $folder = 'tmp-media-'.uniqid('', true);
    $filename = 'test-image.jpg';
    $tempFilePath = "tmp/uploads/{$folder}/{$filename}";
    $metadataPath = "tmp/uploads/{$folder}/metadata.json";

    // Create fake image file
    $image = UploadedFile::fake()->image($filename);
    Storage::disk('local')->put($tempFilePath, file_get_contents($image->getPathname()));
    Storage::disk('local')->put($metadataPath, json_encode([
        'original_name' => $filename,
        'mime_type' => 'image/jpeg',
        'size' => $image->getSize(),
        'collection' => 'content_images',
        'uploaded_at' => now()->toISOString(),
    ]));

    // Create post with HTTPS absolute URL format
    $response = $this->postJson('/api/posts', [
        'title' => 'Test Post with HTTPS Image',
        'content' => '<p>Hello</p><img class="post-content-image" src="https://api.pmone.id/api/tmp-media/'.$folder.'"><p></p>',
        'status' => 'draft',
    ]);

    $response->assertSuccessful();

    $post = Post::find($response->json('data.id'));

    // Content should be updated with permanent URL
    expect($post->content)->not->toContain('/api/tmp-media/');
    expect($post->content)->not->toContain('https://api.pmone.id');
    expect($post->content)->toContain('/storage/');

    // Media record should exist
    expect($post->getMedia('content_images'))->toHaveCount(1);
});

it('handles content with multiple images in different URL formats', function () {
    // Create temporary media files
    $folders = [];
    for ($i = 1; $i <= 3; $i++) {
        $folder = 'tmp-media-'.uniqid('', true);
        $filename = "test-image-{$i}.jpg";
        $tempFilePath = "tmp/uploads/{$folder}/{$filename}";
        $metadataPath = "tmp/uploads/{$folder}/metadata.json";

        $image = UploadedFile::fake()->image($filename);
        Storage::disk('local')->put($tempFilePath, file_get_contents($image->getPathname()));
        Storage::disk('local')->put($metadataPath, json_encode([
            'original_name' => $filename,
            'mime_type' => 'image/jpeg',
            'size' => $image->getSize(),
            'collection' => 'content_images',
            'uploaded_at' => now()->toISOString(),
        ]));

        $folders[] = $folder;
    }

    // Create post with mixed URL formats
    $content = sprintf(
        '<p>Image 1:</p><img src="/api/tmp-media/%s"><p>Image 2:</p><img src="http://localhost:8000/api/tmp-media/%s"><p>Image 3:</p><img src="https://example.com/api/tmp-media/%s">',
        $folders[0],
        $folders[1],
        $folders[2]
    );

    $response = $this->postJson('/api/posts', [
        'title' => 'Test Post with Multiple Images',
        'content' => $content,
        'status' => 'draft',
    ]);

    $response->assertSuccessful();

    $post = Post::find($response->json('data.id'));

    // Content should not contain any temporary URLs
    expect($post->content)->not->toContain('/api/tmp-media/');

    // All three media records should exist
    expect($post->getMedia('content_images'))->toHaveCount(3);
});

it('does not affect content without temporary images', function () {
    $originalContent = '<p>Hello world</p><img src="/storage/media/123/image.jpg"><p>End</p>';

    $response = $this->postJson('/api/posts', [
        'title' => 'Test Post without Temp Images',
        'content' => $originalContent,
        'status' => 'draft',
    ]);

    $response->assertSuccessful();

    $post = Post::find($response->json('data.id'));

    // Content should remain unchanged
    expect($post->content)->toBe($originalContent);

    // No new media should be added
    expect($post->getMedia('content_images'))->toHaveCount(0);
});

it('deletes all media when post is permanently deleted', function () {
    // Create a post with featured image and content images
    $post = Post::factory()->create([
        'created_by' => $this->user->id,
    ]);

    // Add featured image
    $featuredImage = UploadedFile::fake()->image('featured.jpg', 800, 600);
    $post->addMedia($featuredImage)->toMediaCollection('featured_image');

    // Add content images
    $contentImage1 = UploadedFile::fake()->image('content1.jpg', 600, 400);
    $contentImage2 = UploadedFile::fake()->image('content2.jpg', 600, 400);
    $post->addMedia($contentImage1)->toMediaCollection('content_images');
    $post->addMedia($contentImage2)->toMediaCollection('content_images');

    // Verify media exists
    expect($post->getMedia('featured_image'))->toHaveCount(1);
    expect($post->getMedia('content_images'))->toHaveCount(2);

    // Get media IDs before deletion
    $featuredMediaId = $post->getFirstMedia('featured_image')->id;
    $contentMediaIds = $post->getMedia('content_images')->pluck('id')->toArray();

    // Soft delete first
    $post->delete();
    expect(Post::withTrashed()->find($post->id))->not->toBeNull();

    // Media should still exist after soft delete
    expect(\Spatie\MediaLibrary\MediaCollections\Models\Media::find($featuredMediaId))->not->toBeNull();

    // Force delete
    $post->forceDelete();

    // Post should be gone
    expect(Post::withTrashed()->find($post->id))->toBeNull();

    // All media records should be deleted
    expect(\Spatie\MediaLibrary\MediaCollections\Models\Media::find($featuredMediaId))->toBeNull();
    foreach ($contentMediaIds as $mediaId) {
        expect(\Spatie\MediaLibrary\MediaCollections\Models\Media::find($mediaId))->toBeNull();
    }
});

it('does not delete media when post is soft deleted', function () {
    // Create a post with media
    $post = Post::factory()->create([
        'created_by' => $this->user->id,
    ]);

    // Add featured image
    $featuredImage = UploadedFile::fake()->image('featured.jpg', 800, 600);
    $post->addMedia($featuredImage)->toMediaCollection('featured_image');

    $mediaId = $post->getFirstMedia('featured_image')->id;

    // Soft delete
    $post->delete();

    // Post should be soft deleted
    expect(Post::find($post->id))->toBeNull();
    expect(Post::withTrashed()->find($post->id))->not->toBeNull();

    // Media should still exist
    expect(\Spatie\MediaLibrary\MediaCollections\Models\Media::find($mediaId))->not->toBeNull();
});
