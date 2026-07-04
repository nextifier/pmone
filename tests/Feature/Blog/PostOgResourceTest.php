<?php

use App\Exports\PostsExport;
use App\Models\ApiConsumer;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('og_image is null without any OG media', function () {
    $post = Post::factory()->create();

    $this->getJson("/api/posts/{$post->slug}")
        ->assertSuccessful()
        ->assertJsonPath('data.og_image', null)
        ->assertJsonPath('data.og_image_source', null);
});

test('og_image falls back to the generated card', function () {
    $post = Post::factory()->create();
    $post->addMedia(UploadedFile::fake()->image('generated.jpg', 1200, 630))
        ->toMediaCollection('og_image_generated');

    $this->getJson("/api/posts/{$post->slug}")
        ->assertSuccessful()
        ->assertJsonPath('data.og_image', $post->getFirstMediaUrl('og_image_generated'))
        ->assertJsonPath('data.og_image_source', 'generated');
});

test('a manual og_image wins over the generated card', function () {
    $post = Post::factory()->create();
    $post->addMedia(UploadedFile::fake()->image('generated.jpg', 1200, 630))
        ->toMediaCollection('og_image_generated');
    $post->addMedia(UploadedFile::fake()->image('manual.jpg', 1200, 630))
        ->toMediaCollection('og_image');

    $this->getJson("/api/posts/{$post->slug}")
        ->assertSuccessful()
        ->assertJsonPath('data.og_image', $post->getFirstMediaUrl('og_image'))
        ->assertJsonPath('data.og_image_source', 'manual');
});

test('an svg upload is rejected from the og_image collection', function () {
    $post = Post::factory()->create();

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"></svg>';
    Storage::disk('local')->put('tmp/uploads/tmp-ogsvg/og.svg', $svg);
    Storage::disk('local')->put('tmp/uploads/tmp-ogsvg/metadata.json', json_encode([
        'original_name' => 'og.svg',
    ]));

    $this->putJson("/api/posts/{$post->slug}", [
        'tmp_og_image' => 'tmp-ogsvg',
    ]);

    expect($post->fresh()->hasMedia('og_image'))->toBeFalse();
});

test('exports fall back to the generated OG image URL', function () {
    $post = Post::factory()->create();
    $post->addMedia(UploadedFile::fake()->image('generated.jpg', 1200, 630))
        ->toMediaCollection('og_image_generated');
    $post->load(['creator', 'authors', 'tags', 'categories', 'media'])
        ->loadCount(['visits', 'media']);

    $row = (new PostsExport)->map($post);
    $headings = (new PostsExport)->headings();
    $ogColumn = array_search('OG Image URL', $headings, true);

    expect($row[$ogColumn])->toBe($post->getFirstMediaUrl('og_image_generated'));
});

test('the public blog endpoint exposes og_image without the source field', function () {
    ApiConsumer::factory()->create(['api_key' => 'pk_test_og', 'is_active' => true]);

    $post = Post::factory()->create([
        'status' => 'published',
        'visibility' => 'public',
        'published_at' => now()->subDay(),
    ]);
    $post->addMedia(UploadedFile::fake()->image('generated.jpg', 1200, 630))
        ->toMediaCollection('og_image_generated');

    $this->withHeaders(['X-API-Key' => 'pk_test_og'])
        ->getJson("/api/public/blog/posts/{$post->slug}")
        ->assertSuccessful()
        ->assertJsonPath('data.og_image', $post->getFirstMediaUrl('og_image_generated'))
        ->assertJsonMissingPath('data.og_image_source');
});
