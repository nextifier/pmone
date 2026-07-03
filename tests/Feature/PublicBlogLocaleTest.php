<?php

use App\Models\ApiConsumer;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\ResponseCache\Facades\ResponseCache;

uses(RefreshDatabase::class);

beforeEach(function () {
    ResponseCache::clear();

    $this->apiConsumer = ApiConsumer::factory()->create([
        'api_key' => 'pk_test_blog_locale',
        'is_active' => true,
    ]);

    $this->author = User::factory()->create();

    $this->post = Post::factory()->create([
        'title' => ['en' => 'English Title', 'id' => 'Judul Indonesia'],
        'excerpt' => ['en' => 'English excerpt', 'id' => 'Ringkasan Indonesia'],
        'content' => ['en' => '<p>English body</p>', 'id' => '<p>Isi Indonesia</p>'],
        'status' => 'published',
        'visibility' => 'public',
        'created_by' => $this->author->id,
    ]);
});

test('post list defaults to english without a locale param', function () {
    $this->withHeaders(['X-API-Key' => 'pk_test_blog_locale'])
        ->getJson('/api/public/blog/posts')
        ->assertSuccessful()
        ->assertJsonPath('data.0.title', 'English Title')
        ->assertJsonPath('data.0.excerpt', 'English excerpt');
});

test('post list resolves indonesian fields when locale=id', function () {
    $this->withHeaders(['X-API-Key' => 'pk_test_blog_locale'])
        ->getJson('/api/public/blog/posts?locale=id')
        ->assertSuccessful()
        ->assertJsonPath('data.0.title', 'Judul Indonesia')
        ->assertJsonPath('data.0.excerpt', 'Ringkasan Indonesia');
});

test('single post resolves indonesian content when locale=id', function () {
    $this->withHeaders(['X-API-Key' => 'pk_test_blog_locale'])
        ->getJson("/api/public/blog/posts/{$this->post->slug}?locale=id")
        ->assertSuccessful()
        ->assertJsonPath('data.title', 'Judul Indonesia')
        ->assertJsonPath('data.content', '<p>Isi Indonesia</p>')
        ->assertJsonPath('data.meta_title', 'Judul Indonesia');
});

test('an unseeded locale falls back to english', function () {
    $this->withHeaders(['X-API-Key' => 'pk_test_blog_locale'])
        ->getJson("/api/public/blog/posts/{$this->post->slug}?locale=ja")
        ->assertSuccessful()
        ->assertJsonPath('data.title', 'English Title')
        ->assertJsonPath('data.content', '<p>English body</p>');
});

test('an indonesian-only post falls back to indonesian for other locales', function () {
    $post = Post::factory()->create([
        'title' => ['id' => 'Hanya Indonesia'],
        'excerpt' => ['id' => 'Ringkasan saja'],
        'content' => ['id' => '<p>Konten Indonesia saja</p>'],
        'status' => 'published',
        'visibility' => 'public',
        'created_by' => $this->author->id,
    ]);

    $this->withHeaders(['X-API-Key' => 'pk_test_blog_locale'])
        ->getJson("/api/public/blog/posts/{$post->slug}?locale=en")
        ->assertSuccessful()
        ->assertJsonPath('data.title', 'Hanya Indonesia')
        ->assertJsonPath('data.content', '<p>Konten Indonesia saja</p>');
});

test('public responses do not expose translation maps', function () {
    $response = $this->withHeaders(['X-API-Key' => 'pk_test_blog_locale'])
        ->getJson("/api/public/blog/posts/{$this->post->slug}");

    $response->assertSuccessful();

    expect($response->json('data'))->not->toHaveKey('title_translations');
    expect($response->json('data'))->not->toHaveKey('content_translations');
});

test('search endpoint resolves the requested locale', function () {
    $this->withHeaders(['X-API-Key' => 'pk_test_blog_locale'])
        ->getJson('/api/public/blog/posts/search?q=Indonesia&locale=id')
        ->assertSuccessful()
        ->assertJsonPath('data.0.title', 'Judul Indonesia');
});

test('each locale is cached separately', function () {
    $headers = ['X-API-Key' => 'pk_test_blog_locale'];

    $this->withHeaders($headers)->getJson('/api/public/blog/posts?locale=en')
        ->assertJsonPath('data.0.title', 'English Title');

    $this->withHeaders($headers)->getJson('/api/public/blog/posts?locale=id')
        ->assertJsonPath('data.0.title', 'Judul Indonesia');

    // Repeat en to make sure the id response did not overwrite its cache entry
    $this->withHeaders($headers)->getJson('/api/public/blog/posts?locale=en')
        ->assertJsonPath('data.0.title', 'English Title');
});
