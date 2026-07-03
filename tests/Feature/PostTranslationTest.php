<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('user can create a post with locale-keyed translations', function () {
    $response = $this->postJson('/api/posts', [
        'title' => ['en' => 'Hello World', 'id' => 'Halo Dunia'],
        'excerpt' => ['en' => 'An intro', 'id' => 'Sebuah intro'],
        'content' => ['en' => '<p>English body</p>', 'id' => '<p>Isi bahasa Indonesia</p>'],
        'content_format' => 'html',
        'status' => 'draft',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.title', 'Hello World');

    $post = Post::where('title->en', 'Hello World')->first();

    expect($post->getTranslation('title', 'id'))->toBe('Halo Dunia');
    expect($post->getTranslation('content', 'id'))->toBe('<p>Isi bahasa Indonesia</p>');
    expect($post->getTranslations('title'))->toBe(['en' => 'Hello World', 'id' => 'Halo Dunia']);
});

test('plain string payload is still accepted and stored as english', function () {
    $response = $this->postJson('/api/posts', [
        'title' => 'Legacy Title',
        'content' => '<p>Legacy content</p>',
        'status' => 'draft',
    ]);

    $response->assertSuccessful();

    $post = Post::where('title->en', 'Legacy Title')->first();

    expect($post)->not->toBeNull();
    expect($post->getTranslations('title'))->toBe(['en' => 'Legacy Title']);
});

test('a post can be created with indonesian only', function () {
    $response = $this->postJson('/api/posts', [
        'title' => ['id' => 'Hanya Indonesia'],
        'content' => ['id' => '<p>Isi</p>'],
        'status' => 'draft',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.title', 'Hanya Indonesia')
        ->assertJsonPath('data.slug', 'hanya-indonesia');

    $post = Post::where('title->id', 'Hanya Indonesia')->first();
    expect($post->getTranslations('title'))->toBe(['id' => 'Hanya Indonesia']);
});

test('title and content require at least one filled language', function () {
    $this->postJson('/api/posts', [
        'title' => ['id' => null, 'en' => ''],
        'content' => ['id' => null, 'en' => null],
        'status' => 'draft',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['title', 'content']);
});

test('updating one locale preserves untouched locales', function () {
    $post = Post::factory()->create([
        'title' => ['en' => 'Original', 'id' => 'Asli', 'ja' => 'オリジナル'],
        'content' => ['en' => '<p>Body</p>'],
        'created_by' => $this->user->id,
    ]);

    $response = $this->putJson("/api/posts/{$post->slug}", [
        'title' => ['en' => 'Updated'],
    ]);

    $response->assertSuccessful();

    $post->refresh();
    expect($post->getTranslation('title', 'en'))->toBe('Updated');
    expect($post->getTranslation('title', 'id'))->toBe('Asli');
    expect($post->getTranslation('title', 'ja'))->toBe('オリジナル');
});

test('sending null clears a locale value', function () {
    $post = Post::factory()->create([
        'title' => ['en' => 'Original', 'id' => 'Asli'],
        'created_by' => $this->user->id,
    ]);

    $this->putJson("/api/posts/{$post->slug}", [
        'title' => ['en' => 'Original', 'id' => null],
    ])->assertSuccessful();

    expect($post->fresh()->getTranslations('title'))->toBe(['en' => 'Original']);
});

test('meta fields are auto-generated per locale', function () {
    $post = Post::factory()->create([
        'title' => ['en' => 'English Title', 'id' => 'Judul Indonesia'],
        'excerpt' => ['en' => 'English excerpt', 'id' => 'Ringkasan Indonesia'],
        'content' => ['en' => '<p>Body</p>'],
        'meta_title' => null,
        'meta_description' => null,
    ]);

    expect($post->getTranslation('meta_title', 'en'))->toBe('English Title');
    expect($post->getTranslation('meta_title', 'id'))->toBe('Judul Indonesia');
    expect($post->getTranslation('meta_description', 'en'))->toBe('English excerpt');
    expect($post->getTranslation('meta_description', 'id'))->toBe('Ringkasan Indonesia');
});

test('slug is generated from the english title', function () {
    $response = $this->postJson('/api/posts', [
        'title' => ['en' => 'Slug Source Title', 'id' => 'Judul Sumber Slug'],
        'content' => ['en' => '<p>Body</p>'],
        'status' => 'draft',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.slug', 'slug-source-title');
});

test('admin detail response includes translation maps', function () {
    $post = Post::factory()->create([
        'title' => ['en' => 'Detail Title', 'id' => 'Judul Detail'],
        'excerpt' => ['en' => 'Excerpt', 'id' => 'Ringkasan'],
        'content' => ['en' => '<p>Body en</p>', 'id' => '<p>Isi id</p>'],
        'created_by' => $this->user->id,
    ]);

    $response = $this->getJson("/api/posts/{$post->slug}");

    $response->assertSuccessful()
        ->assertJsonPath('data.title', 'Detail Title')
        ->assertJsonPath('data.title_translations.id', 'Judul Detail')
        ->assertJsonPath('data.excerpt_translations.id', 'Ringkasan')
        ->assertJsonPath('data.content_translations.id', '<p>Isi id</p>');
});

test('search matches content in any locale', function () {
    Post::factory()->create([
        'title' => ['en' => 'Building Materials', 'id' => 'Bahan Bangunan'],
        'content' => ['en' => '<p>Body</p>'],
        'created_by' => $this->user->id,
    ]);
    Post::factory()->create([
        'title' => ['en' => 'Other Topic'],
        'content' => ['en' => '<p>Body</p>'],
        'created_by' => $this->user->id,
    ]);

    $this->getJson('/api/posts?filter_search=Bangunan')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Building Materials');
});

test('sorting by title works on json columns', function () {
    Post::factory()->create([
        'title' => ['en' => 'Bravo'],
        'content' => ['en' => '<p>Body</p>'],
        'created_by' => $this->user->id,
    ]);
    Post::factory()->create([
        'title' => ['en' => 'Alpha'],
        'content' => ['en' => '<p>Body</p>'],
        'created_by' => $this->user->id,
    ]);

    $this->getJson('/api/posts?sort=title')
        ->assertSuccessful()
        ->assertJsonPath('data.0.title', 'Alpha')
        ->assertJsonPath('data.1.title', 'Bravo');
});
