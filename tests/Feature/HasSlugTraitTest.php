<?php

use App\Models\Brand;
use App\Models\Event;
use App\Models\Form;
use App\Models\Post;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

// ─── Auto-generation from source field ───────────────────────────────

it('auto-generates slug from title on create', function () {
    $post = Post::create([
        'title' => 'My Great Article',
        'content' => 'Content here',
        'status' => 'draft',
        'content_format' => 'html',
        'visibility' => 'public',
        'source' => 'native',
    ]);

    expect($post->slug)->toBe('my-great-article');
});

it('auto-generates slug from name for Brand', function () {
    $brand = Brand::factory()->create(['name' => 'Acme Corporation']);

    expect($brand->slug)->toBe('acme-corporation');
});

// ─── Unique suffix on auto-generation ────────────────────────────────

it('appends suffix when auto-generated slug already exists', function () {
    Post::create([
        'title' => 'Duplicate',
        'content' => 'First',
        'status' => 'draft',
        'content_format' => 'html',
        'visibility' => 'public',
        'source' => 'native',
    ]);

    $second = Post::create([
        'title' => 'Duplicate',
        'content' => 'Second',
        'status' => 'draft',
        'content_format' => 'html',
        'visibility' => 'public',
        'source' => 'native',
    ]);

    $third = Post::create([
        'title' => 'Duplicate',
        'content' => 'Third',
        'status' => 'draft',
        'content_format' => 'html',
        'visibility' => 'public',
        'source' => 'native',
    ]);

    expect($second->slug)->toBe('duplicate-1');
    expect($third->slug)->toBe('duplicate-2');
});

// ─── BUG 1 FIX: User-provided duplicate slug gets auto-suffixed ─────

it('auto-suffixes user-provided slug that already exists on create', function () {
    Post::create([
        'title' => 'First Post',
        'slug' => 'my-custom-slug',
        'content' => 'Content',
        'status' => 'draft',
        'content_format' => 'html',
        'visibility' => 'public',
        'source' => 'native',
    ]);

    $second = Post::create([
        'title' => 'Second Post',
        'slug' => 'my-custom-slug',
        'content' => 'Content',
        'status' => 'draft',
        'content_format' => 'html',
        'visibility' => 'public',
        'source' => 'native',
    ]);

    expect($second->slug)->toBe('my-custom-slug-1');
});

it('auto-suffixes user-provided slug that already exists on update', function () {
    Post::create([
        'title' => 'First Post',
        'slug' => 'taken-slug',
        'content' => 'Content',
        'status' => 'draft',
        'content_format' => 'html',
        'visibility' => 'public',
        'source' => 'native',
    ]);

    $second = Post::create([
        'title' => 'Second Post',
        'content' => 'Content',
        'status' => 'draft',
        'content_format' => 'html',
        'visibility' => 'public',
        'source' => 'native',
    ]);

    $second->slug = 'taken-slug';
    $second->save();

    expect($second->fresh()->slug)->toBe('taken-slug-1');
});

it('allows model to keep its own slug on update', function () {
    $post = Post::create([
        'title' => 'My Post',
        'slug' => 'my-post',
        'content' => 'Content',
        'status' => 'draft',
        'content_format' => 'html',
        'visibility' => 'public',
        'source' => 'native',
    ]);

    $post->update(['content' => 'Updated content']);

    expect($post->fresh()->slug)->toBe('my-post');
});

it('keeps user-provided unique slug as-is', function () {
    $post = Post::create([
        'title' => 'Some Title',
        'slug' => 'totally-custom',
        'content' => 'Content',
        'status' => 'draft',
        'content_format' => 'html',
        'visibility' => 'public',
        'source' => 'native',
    ]);

    expect($post->slug)->toBe('totally-custom');
});

// ─── Non-Latin transliteration (CJK, Hangul, Arabic, Thai, ...) ──────

it('transliterates non-Latin names into a meaningful slug', function (string $name, string $expected) {
    $brand = Brand::factory()->create(['name' => $name]);

    expect($brand->slug)->toBe($expected);
})->with([
    'chinese' => ['柒树', 'qi-shu'],
    'chinese 2' => ['舟聪', 'zhou-cong'],
    'korean' => ['안녕', 'annyeong'],
    'thai' => ['สวัสดี', 'swasdi'],
]);

it('keeps transliterated slugs unique when two names collide', function () {
    $first = Brand::factory()->create(['name' => '柒树']);
    $second = Brand::factory()->create(['name' => '柒树']);

    expect($first->slug)->toBe('qi-shu');
    expect($second->slug)->toBe('qi-shu-1');
});

it('falls back to a non-empty slug when the name has no sluggable characters', function () {
    $brand = Brand::factory()->create(['name' => '🎉🎊']);

    expect($brand->slug)->toBe('brand');
});

// ─── Scoped uniqueness ───────────────────────────────────────────────

it('scopes slug uniqueness per project_id for Event', function () {
    $project1 = Project::factory()->create();
    $project2 = Project::factory()->create();

    Event::factory()->create([
        'title' => 'Same Title',
        'slug' => 'same-title',
        'project_id' => $project1->id,
    ]);

    // Same slug in different project should be allowed
    $event2 = Event::factory()->create([
        'title' => 'Same Title',
        'project_id' => $project2->id,
    ]);

    expect($event2->slug)->toBe('same-title');

    // Same slug in same project should get suffix
    $event3 = Event::factory()->create([
        'title' => 'Same Title',
        'project_id' => $project1->id,
    ]);

    expect($event3->slug)->toBe('same-title-1');
});

// ─── includeTrashed ──────────────────────────────────────────────────

it('checks trashed records for Brand slug uniqueness', function () {
    $brand = Brand::factory()->create(['name' => 'Trashed Brand']);
    $brand->delete();

    $newBrand = Brand::factory()->create(['name' => 'Trashed Brand']);

    expect($newBrand->slug)->toBe('trashed-brand-1');
});

it('checks trashed records by default for soft-deleting models without explicit config', function () {
    $post = Post::create([
        'title' => 'Trashed Slug',
        'content' => 'Content',
        'status' => 'draft',
        'content_format' => 'html',
        'visibility' => 'public',
        'source' => 'native',
    ]);
    $post->delete();

    $second = Post::create([
        'title' => 'Trashed Slug',
        'content' => 'Content',
        'status' => 'draft',
        'content_format' => 'html',
        'visibility' => 'public',
        'source' => 'native',
    ]);

    expect($second->slug)->toBe('trashed-slug-1');
});

// ─── Slug not regenerated on update when onUpdate=false ──────────────

it('does not change slug when title changes and onUpdate is false', function () {
    $post = Post::create([
        'title' => 'Original Title',
        'content' => 'Content',
        'status' => 'draft',
        'content_format' => 'html',
        'visibility' => 'public',
        'source' => 'native',
    ]);

    expect($post->slug)->toBe('original-title');

    $post->update(['title' => 'New Title']);

    expect($post->fresh()->slug)->toBe('original-title');
});

// ─── Empty slug triggers regeneration ────────────────────────────────

it('regenerates slug when slug is set to empty on update', function () {
    $post = Post::create([
        'title' => 'My Title',
        'content' => 'Content',
        'status' => 'draft',
        'content_format' => 'html',
        'visibility' => 'public',
        'source' => 'native',
    ]);

    $post->slug = '';
    $post->save();

    expect($post->fresh()->slug)->toBe('my-title');
});

// ─── False positive LIKE match handled correctly ─────────────────────

it('handles similar slug names without false suffix collision', function () {
    Post::create([
        'title' => 'Test',
        'content' => 'Content',
        'status' => 'draft',
        'content_format' => 'html',
        'visibility' => 'public',
        'source' => 'native',
    ]);

    // This slug starts with "test-" but is NOT a numeric suffix variant
    Post::create([
        'title' => 'Test Article',
        'content' => 'Content',
        'status' => 'draft',
        'content_format' => 'html',
        'visibility' => 'public',
        'source' => 'native',
    ]);

    // New "Test" should get suffix -1, not -2
    $third = Post::create([
        'title' => 'Test',
        'content' => 'Content',
        'status' => 'draft',
        'content_format' => 'html',
        'visibility' => 'public',
        'source' => 'native',
    ]);

    expect($third->slug)->toBe('test-1');
});

// ─── Form model (global unique, no scoping) ──────────────────────────

it('auto-generates unique slug for Form model', function () {
    Form::factory()->create(['title' => 'Contact Us', 'user_id' => $this->user->id]);
    $second = Form::factory()->create(['title' => 'Contact Us', 'user_id' => $this->user->id]);

    expect($second->slug)->toBe('contact-us-1');
});

// ─── User-provided duplicate slug on scoped model ────────────────────

it('auto-suffixes user-provided duplicate slug on scoped model', function () {
    $project = Project::factory()->create();

    Event::factory()->create([
        'title' => 'Event A',
        'slug' => 'my-event',
        'project_id' => $project->id,
    ]);

    $event2 = Event::factory()->create([
        'title' => 'Event B',
        'slug' => 'my-event',
        'project_id' => $project->id,
    ]);

    expect($event2->slug)->toBe('my-event-1');
});
