<?php

use App\Models\Project;
use App\Models\User;
use App\Models\WebsitePage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\ResponseCache\Facades\ResponseCache;

uses(RefreshDatabase::class);

beforeEach(function () {
    ResponseCache::clear();

    $masterRole = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'projects.update', 'guard_name' => 'web']);
    $masterRole->syncPermissions(Permission::all());

    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole('master');

    $this->project = Project::factory()->create(['username' => 'acme']);

    $this->indexEndpoint = "/api/projects/{$this->project->username}/website-pages";
});

test('index lists all six page keys even when none are configured', function () {
    $this->actingAs($this->admin)
        ->getJson($this->indexEndpoint)
        ->assertSuccessful()
        ->assertJsonCount(6, 'data')
        ->assertJsonPath('data.terms.body', [])
        ->assertJsonPath('data.privacy.key', 'privacy');

    expect(array_keys($this->actingAs($this->admin)->getJson($this->indexEndpoint)->json('data')))
        ->toBe(WebsitePage::KEYS);
});

test('index returns saved translations for a configured page', function () {
    WebsitePage::factory()->create([
        'project_id' => $this->project->id,
        'key' => 'terms',
        'body' => ['en' => '<p>English terms</p>', 'id' => '<p>Syarat Indonesia</p>'],
    ]);

    $this->actingAs($this->admin)
        ->getJson($this->indexEndpoint)
        ->assertSuccessful()
        ->assertJsonPath('data.terms.body.en', '<p>English terms</p>')
        ->assertJsonPath('data.terms.body.id', '<p>Syarat Indonesia</p>')
        ->assertJsonPath('data.privacy.body', []);
});

test('update creates a new page row on first save', function () {
    $this->actingAs($this->admin)
        ->putJson("{$this->indexEndpoint}/privacy", [
            'body' => ['en' => '<p>Privacy body</p>', 'id' => null],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.key', 'privacy')
        ->assertJsonPath('data.body.en', '<p>Privacy body</p>');

    $page = WebsitePage::query()->where('project_id', $this->project->id)->where('key', 'privacy')->first();
    expect($page)->not->toBeNull();
    expect($page->getTranslation('body', 'en'))->toBe('<p>Privacy body</p>');
});

test('update upserts an existing page preserving the unique (project_id, key) row', function () {
    $page = WebsitePage::factory()->create([
        'project_id' => $this->project->id,
        'key' => 'terms',
        'body' => ['en' => '<p>Old</p>', 'id' => '<p>Lama</p>'],
    ]);

    $this->actingAs($this->admin)
        ->putJson("{$this->indexEndpoint}/terms", [
            'body' => ['en' => '<p>New</p>', 'id' => '<p>Baru</p>'],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.body.en', '<p>New</p>');

    expect(WebsitePage::query()->where('project_id', $this->project->id)->where('key', 'terms')->count())->toBe(1);
    expect($page->fresh()->getTranslation('body', 'en'))->toBe('<p>New</p>');
});

test('update rejects an unknown page key', function () {
    $this->actingAs($this->admin)
        ->putJson("{$this->indexEndpoint}/not-a-real-page", [
            'body' => ['en' => '<p>x</p>'],
        ])
        ->assertNotFound();
});

test('update requires the body field', function () {
    $this->actingAs($this->admin)
        ->putJson("{$this->indexEndpoint}/terms", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['body']);
});

test('a locale can be cleared back to null (fail-open editing)', function () {
    WebsitePage::factory()->create([
        'project_id' => $this->project->id,
        'key' => 'terms',
        'body' => ['en' => '<p>English</p>', 'id' => '<p>Indonesian</p>'],
    ]);

    $this->actingAs($this->admin)
        ->putJson("{$this->indexEndpoint}/terms", [
            'body' => ['en' => '<p>English</p>', 'id' => null],
        ])
        ->assertSuccessful();

    // Spatie stores a cleared translation as an empty string; what matters for
    // fail-open is that the public read (which uses filled()) surfaces it as
    // null so the site renders its baked copy - asserted in
    // PublicWebsitePageTest. Here we assert the stored value is blank.
    $page = WebsitePage::query()->where('project_id', $this->project->id)->where('key', 'terms')->first();
    expect($page->getTranslation('body', 'id', false))->toBeEmpty();
});

test('returns 401 without authentication', function () {
    $this->getJson($this->indexEndpoint)->assertUnauthorized();
    $this->putJson("{$this->indexEndpoint}/terms", ['body' => ['en' => 'x']])->assertUnauthorized();
});

test('a user without projects.update cannot save a page', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->putJson("{$this->indexEndpoint}/terms", ['body' => ['en' => '<p>x</p>']])
        ->assertForbidden();
});

test('WebsitePage tags itself for the website-pages response cache, mirroring Faq', function () {
    $reflection = new ReflectionClass(WebsitePage::class);
    $method = $reflection->getMethod('responseCacheTags');
    $method->setAccessible(true);

    expect($method->invoke(null))->toBe(['website-pages']);
});
