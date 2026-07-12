<?php

use App\Models\Project;
use App\Models\User;
use App\Models\WebsiteCopy;
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

    $this->indexEndpoint = "/api/projects/{$this->project->username}/website-copy";
});

test('index lists the full page x field grid even when none are configured', function () {
    $response = $this->actingAs($this->admin)
        ->getJson($this->indexEndpoint)
        ->assertSuccessful()
        ->assertJsonPath('data.home.title', [])
        ->assertJsonPath('data.home.description', [])
        ->assertJsonPath('data.brands.title', [])
        ->assertJsonPath('data.brands.description', []);

    // The widened whitelist (plan 012 generalization): every base content.js
    // page key is present in the grid, not just home/brands.
    expect($response->json('data'))->toHaveKeys(WebsiteCopy::PAGE_KEYS);
    expect($response->json('data.winner.title'))->toBe([]);
});

test('index returns saved translations for a configured key', function () {
    WebsiteCopy::factory()->create([
        'project_id' => $this->project->id,
        'key' => WebsiteCopy::keyFor('home', 'title'),
        'value' => ['en' => 'English home title', 'id' => 'Judul beranda'],
    ]);

    $this->actingAs($this->admin)
        ->getJson($this->indexEndpoint)
        ->assertSuccessful()
        ->assertJsonPath('data.home.title.en', 'English home title')
        ->assertJsonPath('data.home.title.id', 'Judul beranda')
        ->assertJsonPath('data.home.description', []);
});

test('update creates a new row on first save', function () {
    $this->actingAs($this->admin)
        ->putJson("{$this->indexEndpoint}/home/title", [
            'value' => ['en' => 'New home title', 'id' => null],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.key', 'pages.home.title')
        ->assertJsonPath('data.value.en', 'New home title');

    $row = WebsiteCopy::query()->where('project_id', $this->project->id)->where('key', 'pages.home.title')->first();
    expect($row)->not->toBeNull();
    expect($row->getTranslation('value', 'en'))->toBe('New home title');
});

test('update upserts an existing row preserving the unique (project_id, key) row', function () {
    $row = WebsiteCopy::factory()->create([
        'project_id' => $this->project->id,
        'key' => WebsiteCopy::keyFor('brands', 'description'),
        'value' => ['en' => 'Old', 'id' => 'Lama'],
    ]);

    $this->actingAs($this->admin)
        ->putJson("{$this->indexEndpoint}/brands/description", [
            'value' => ['en' => 'New', 'id' => 'Baru'],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.value.en', 'New');

    expect(WebsiteCopy::query()->where('project_id', $this->project->id)->where('key', 'pages.brands.description')->count())->toBe(1);
    expect($row->fresh()->getTranslation('value', 'en'))->toBe('New');
});

test('update saves a page key beyond the original home/brands spike scope', function () {
    $this->actingAs($this->admin)
        ->putJson("{$this->indexEndpoint}/winner/title", [
            'value' => ['en' => 'Random Winner Generator'],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.key', 'pages.winner.title')
        ->assertJsonPath('data.value.en', 'Random Winner Generator');

    expect(WebsiteCopy::query()->where('project_id', $this->project->id)->where('key', 'pages.winner.title')->exists())->toBeTrue();
});

test('update rejects an unknown page key', function () {
    $this->actingAs($this->admin)
        ->putJson("{$this->indexEndpoint}/not-a-real-page/title", [
            'value' => ['en' => 'x'],
        ])
        ->assertNotFound();
});

test('update rejects an unknown field', function () {
    $this->actingAs($this->admin)
        ->putJson("{$this->indexEndpoint}/home/not-a-real-field", [
            'value' => ['en' => 'x'],
        ])
        ->assertNotFound();
});

test('update requires the value field', function () {
    $this->actingAs($this->admin)
        ->putJson("{$this->indexEndpoint}/home/title", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['value']);
});

test('update rejects a value over the 300-char limit', function () {
    $this->actingAs($this->admin)
        ->putJson("{$this->indexEndpoint}/home/title", [
            'value' => ['en' => str_repeat('a', 301)],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['value.en']);
});

test('a locale can be cleared back to null (fail-open editing)', function () {
    WebsiteCopy::factory()->create([
        'project_id' => $this->project->id,
        'key' => WebsiteCopy::keyFor('home', 'title'),
        'value' => ['en' => 'English', 'id' => 'Indonesian'],
    ]);

    $this->actingAs($this->admin)
        ->putJson("{$this->indexEndpoint}/home/title", [
            'value' => ['en' => 'English', 'id' => null],
        ])
        ->assertSuccessful();

    $row = WebsiteCopy::query()->where('project_id', $this->project->id)->where('key', 'pages.home.title')->first();
    expect($row->getTranslation('value', 'id', false))->toBeEmpty();
});

test('returns 401 without authentication', function () {
    $this->getJson($this->indexEndpoint)->assertUnauthorized();
    $this->putJson("{$this->indexEndpoint}/home/title", ['value' => ['en' => 'x']])->assertUnauthorized();
});

test('a user without projects.update cannot save copy', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->putJson("{$this->indexEndpoint}/home/title", ['value' => ['en' => 'x']])
        ->assertForbidden();
});

test('WebsiteCopy tags itself for the website-settings response cache (it rides that payload, not a dedicated endpoint)', function () {
    $reflection = new ReflectionClass(WebsiteCopy::class);
    $method = $reflection->getMethod('responseCacheTags');
    $method->setAccessible(true);

    expect($method->invoke(null))->toBe(['website-settings']);
});
