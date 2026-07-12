<?php

use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\Post;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    ApiConsumer::factory()->create(['api_key' => 'pk_clamp', 'is_active' => true]);
    $this->headers = ['X-API-Key' => 'pk_clamp'];
});

// ── PublicBlogController ────────────────────────────────────────────────────

it('clamps blog posts per_page to the raised ceiling for over-large values', function () {
    $this->withHeaders($this->headers)
        ->getJson('/api/public/blog/posts?per_page=100000')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 1000);
});

it('clamps blog featured per_page to the standard ceiling and floor', function () {
    // Over-large clamps down to the 100 ceiling.
    $this->withHeaders($this->headers)
        ->getJson('/api/public/blog/posts/featured?per_page=100000')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100);

    // Zero/negative clamps up to the minimum of 1.
    $this->withHeaders($this->headers)
        ->getJson('/api/public/blog/posts/featured?per_page=0')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 1);

    // Absent keeps the endpoint's own default (10).
    $this->withHeaders($this->headers)
        ->getJson('/api/public/blog/posts/featured')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 10);
});

// ── PublicProjectController ─────────────────────────────────────────────────

it('clamps project events per_page to the standard ceiling and floor', function () {
    $project = Project::factory()->create();
    Event::factory()->published()->create(['project_id' => $project->id]);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$project->username}/events?per_page=100000")
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$project->username}/events?per_page=-5")
        ->assertOk()
        ->assertJsonPath('meta.per_page', 1);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$project->username}/events")
        ->assertOk()
        ->assertJsonPath('meta.per_page', 15);
});

it('clamps active brands per_page to the raised ceiling (sitemap needs the headroom)', function () {
    $project = Project::factory()->create();
    Event::factory()->published()->create([
        'project_id' => $project->id,
        'is_active' => true,
    ]);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$project->username}/brands?per_page=100000")
        ->assertOk()
        ->assertJsonPath('meta.per_page', 1000);

    // The sitemap's own per_page=1000 is honored unchanged.
    $this->withHeaders($this->headers)
        ->getJson("/api/public/projects/{$project->username}/brands?per_page=1000")
        ->assertOk()
        ->assertJsonPath('meta.per_page', 1000);
});

it('leaves default blog pagination unchanged when per_page is absent', function () {
    Post::factory()->count(3)->create([
        'status' => 'published',
        'visibility' => 'public',
        'created_by' => User::factory()->create()->id,
    ]);

    $this->withHeaders($this->headers)
        ->getJson('/api/public/blog/posts')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 15)
        ->assertJsonCount(3, 'data');
});
