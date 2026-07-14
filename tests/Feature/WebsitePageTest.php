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

test('index exposes each page last_updated_at and the project website url', function () {
    $this->project->links()->create(['label' => 'Website', 'url' => 'https://acme.example']);
    WebsitePage::factory()->create([
        'project_id' => $this->project->id,
        'key' => 'terms',
        'body' => ['en' => '<p>Terms</p>'],
        'last_updated_at' => '2026-01-15',
    ]);

    $this->actingAs($this->admin)
        ->getJson($this->indexEndpoint)
        ->assertSuccessful()
        ->assertJsonPath('website_url', 'https://acme.example')
        ->assertJsonPath('data.terms.last_updated_at', '2026-01-15')
        ->assertJsonPath('data.privacy.last_updated_at', null);
});

test('update saves last_updated_at without disturbing the body translations', function () {
    WebsitePage::factory()->create([
        'project_id' => $this->project->id,
        'key' => 'terms',
        'body' => ['en' => '<p>English</p>', 'id' => '<p>Indonesian</p>'],
    ]);

    $this->actingAs($this->admin)
        ->putJson("{$this->indexEndpoint}/terms", [
            'body' => ['en' => '<p>English</p>', 'id' => '<p>Indonesian</p>'],
            'last_updated_at' => '2026-03-01',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.last_updated_at', '2026-03-01')
        ->assertJsonPath('data.body.en', '<p>English</p>')
        ->assertJsonPath('data.body.id', '<p>Indonesian</p>');

    $page = WebsitePage::query()->where('project_id', $this->project->id)->where('key', 'terms')->first();
    expect($page->last_updated_at->toDateString())->toBe('2026-03-01');
    expect($page->getTranslation('body', 'id'))->toBe('<p>Indonesian</p>');
});

test('update accepts a date with a blank body (date-only save stays fail-open)', function () {
    $this->actingAs($this->admin)
        ->putJson("{$this->indexEndpoint}/privacy", [
            'body' => ['en' => null, 'id' => null, 'ja' => null, 'ko' => null, 'zh' => null],
            'last_updated_at' => '2026-04-10',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.last_updated_at', '2026-04-10');

    $page = WebsitePage::query()->where('project_id', $this->project->id)->where('key', 'privacy')->first();
    expect($page)->not->toBeNull();
    expect($page->getTranslation('body', 'en', false))->toBeEmpty();
});

test('update rejects an invalid last_updated_at', function () {
    $this->actingAs($this->admin)
        ->putJson("{$this->indexEndpoint}/terms", [
            'body' => ['en' => '<p>x</p>'],
            'last_updated_at' => 'not-a-date',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['last_updated_at']);
});

test('template returns the built-in copy with project identity interpolated', function () {
    $this->project->update(['settings' => [
        'website_settings' => [
            'site_config' => ['identity' => [
                'company_name' => 'Acme Events Ltd',
                'company_address' => 'Jakarta, Indonesia',
            ]],
        ],
    ]]);

    $body = $this->actingAs($this->admin)
        ->getJson("{$this->indexEndpoint}/terms/template")
        ->assertSuccessful()
        ->json('data.body');

    expect($body)
        ->toContain('Acme Events Ltd')
        ->toContain('Jakarta, Indonesia')
        ->not->toContain('{company_name}')
        ->not->toContain('{company_address}');
});

test('template blanks unset placeholders instead of leaking raw tokens', function () {
    $body = $this->actingAs($this->admin)
        ->getJson("{$this->indexEndpoint}/terms/template")
        ->assertSuccessful()
        ->json('data.body');

    expect($body)
        ->not->toContain('{company_name}')
        ->not->toContain('{company_address}')
        ->not->toContain('{website_name}')
        ->not->toContain('{contact_email}');
});

test('template rejects an unknown page key', function () {
    $this->actingAs($this->admin)
        ->getJson("{$this->indexEndpoint}/not-a-real-page/template")
        ->assertNotFound();
});

test('template requires authentication and projects.update', function () {
    $this->getJson("{$this->indexEndpoint}/terms/template")->assertUnauthorized();

    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user)
        ->getJson("{$this->indexEndpoint}/terms/template")
        ->assertForbidden();
});
