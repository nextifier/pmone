<?php

use App\Models\ApiConsumer;
use App\Models\Project;
use App\Models\WebsitePage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\ResponseCache\Facades\ResponseCache;

uses(RefreshDatabase::class);

beforeEach(function () {
    ResponseCache::clear();

    ApiConsumer::factory()->create(['api_key' => 'pk_test_website_pages', 'is_active' => true]);

    $this->project = Project::factory()->create(['username' => 'acme']);

    $this->endpoint = "/api/public/projects/{$this->project->username}/website-pages";
});

test('returns 401 without api key', function () {
    $this->getJson($this->endpoint)->assertUnauthorized();
});

test('an unconfigured project fails open with body null for every key', function () {
    $response = $this->withHeaders(['X-API-Key' => 'pk_test_website_pages'])
        ->getJson($this->endpoint)
        ->assertSuccessful();

    foreach (WebsitePage::KEYS as $key) {
        $response->assertJsonPath("data.{$key}.body", null);
    }

    expect(array_keys($response->json('data')))->toBe(WebsitePage::KEYS);
});

test('returns the saved body for the requested locale', function () {
    WebsitePage::factory()->create([
        'project_id' => $this->project->id,
        'key' => 'terms',
        'body' => ['en' => '<p>English terms</p>', 'id' => '<p>Syarat Indonesia</p>'],
    ]);

    $this->withHeaders(['X-API-Key' => 'pk_test_website_pages'])
        ->getJson($this->endpoint.'?locale=id')
        ->assertSuccessful()
        ->assertJsonPath('data.terms.body', '<p>Syarat Indonesia</p>');

    $this->withHeaders(['X-API-Key' => 'pk_test_website_pages'])
        ->getJson($this->endpoint.'?locale=en')
        ->assertSuccessful()
        ->assertJsonPath('data.terms.body', '<p>English terms</p>');
});

test('a locale with no saved translation fails open to null (not another locale)', function () {
    WebsitePage::factory()->create([
        'project_id' => $this->project->id,
        'key' => 'terms',
        'body' => ['en' => '<p>English only</p>'],
    ]);

    $this->withHeaders(['X-API-Key' => 'pk_test_website_pages'])
        ->getJson($this->endpoint.'?locale=ja')
        ->assertSuccessful()
        ->assertJsonPath('data.terms.body', null);
});

test('sibling keys stay null when only one page is configured', function () {
    WebsitePage::factory()->create([
        'project_id' => $this->project->id,
        'key' => 'terms',
        'body' => ['en' => '<p>Terms</p>'],
    ]);

    $this->withHeaders(['X-API-Key' => 'pk_test_website_pages'])
        ->getJson($this->endpoint.'?locale=en')
        ->assertSuccessful()
        ->assertJsonPath('data.terms.body', '<p>Terms</p>')
        ->assertJsonPath('data.privacy.body', null)
        ->assertJsonPath('data.event-policy.body', null)
        ->assertJsonPath('data.help-center.body', null)
        ->assertJsonPath('data.ticket-terms-and-conditions.body', null)
        ->assertJsonPath('data.ticket-refund-and-return-policy.body', null);
});

test('a page belonging to another project is never leaked', function () {
    $otherProject = Project::factory()->create(['username' => 'other']);
    WebsitePage::factory()->create([
        'project_id' => $otherProject->id,
        'key' => 'terms',
        'body' => ['en' => '<p>Other project terms</p>'],
    ]);

    $this->withHeaders(['X-API-Key' => 'pk_test_website_pages'])
        ->getJson($this->endpoint.'?locale=en')
        ->assertSuccessful()
        ->assertJsonPath('data.terms.body', null);
});

test('a page returns its own last_update date', function () {
    WebsitePage::factory()->create([
        'project_id' => $this->project->id,
        'key' => 'terms',
        'body' => ['en' => '<p>Terms</p>'],
        'last_updated_at' => '2026-02-20',
    ]);

    $this->withHeaders(['X-API-Key' => 'pk_test_website_pages'])
        ->getJson($this->endpoint.'?locale=en')
        ->assertSuccessful()
        ->assertJsonPath('data.terms.last_update', '2026-02-20');
});

test('a page with no date falls back to the legacy project-level terms date', function () {
    $this->project->update(['settings' => [
        'website_settings' => ['terms' => ['last_update' => '2025-12-01']],
    ]]);

    // A page with a body but no explicit date inherits the legacy date...
    WebsitePage::factory()->create([
        'project_id' => $this->project->id,
        'key' => 'terms',
        'body' => ['en' => '<p>Terms</p>'],
    ]);

    $response = $this->withHeaders(['X-API-Key' => 'pk_test_website_pages'])
        ->getJson($this->endpoint.'?locale=en')
        ->assertSuccessful();

    // ...and so does an entirely unconfigured page.
    $response->assertJsonPath('data.terms.last_update', '2025-12-01');
    $response->assertJsonPath('data.privacy.last_update', '2025-12-01');
});

test('a per-page date overrides the legacy project-level date', function () {
    $this->project->update(['settings' => [
        'website_settings' => ['terms' => ['last_update' => '2025-12-01']],
    ]]);
    WebsitePage::factory()->create([
        'project_id' => $this->project->id,
        'key' => 'terms',
        'body' => ['en' => '<p>Terms</p>'],
        'last_updated_at' => '2026-06-06',
    ]);

    $this->withHeaders(['X-API-Key' => 'pk_test_website_pages'])
        ->getJson($this->endpoint.'?locale=en')
        ->assertSuccessful()
        ->assertJsonPath('data.terms.last_update', '2026-06-06')
        ->assertJsonPath('data.privacy.last_update', '2025-12-01');
});

test('last_update is null when neither a page date nor a legacy date exists', function () {
    $response = $this->withHeaders(['X-API-Key' => 'pk_test_website_pages'])
        ->getJson($this->endpoint.'?locale=en')
        ->assertSuccessful();

    foreach (WebsitePage::KEYS as $key) {
        $response->assertJsonPath("data.{$key}.last_update", null);
    }
});

test('a visually-empty HTML body fails open to null (never an empty override)', function () {
    WebsitePage::factory()->create([
        'project_id' => $this->project->id,
        'key' => 'terms',
        'body' => ['en' => '<p></p>', 'id' => '<p>&nbsp;</p>', 'ja' => '<p>Real terms</p>'],
    ]);

    // "<p></p>" and "<p>&nbsp;</p>" have no visible text -> null (baked copy renders).
    $this->withHeaders(['X-API-Key' => 'pk_test_website_pages'])
        ->getJson($this->endpoint.'?locale=en')
        ->assertSuccessful()
        ->assertJsonPath('data.terms.body', null);

    $this->withHeaders(['X-API-Key' => 'pk_test_website_pages'])
        ->getJson($this->endpoint.'?locale=id')
        ->assertSuccessful()
        ->assertJsonPath('data.terms.body', null);

    // A locale with real text is still served.
    $this->withHeaders(['X-API-Key' => 'pk_test_website_pages'])
        ->getJson($this->endpoint.'?locale=ja')
        ->assertSuccessful()
        ->assertJsonPath('data.terms.body', '<p>Real terms</p>');
});

test('body is locale-scoped but last_update is identical across locales', function () {
    WebsitePage::factory()->create([
        'project_id' => $this->project->id,
        'key' => 'terms',
        'body' => ['en' => '<p>English terms</p>', 'ja' => '<p>日本語</p>'],
        'last_updated_at' => '2026-05-05',
    ]);

    // English: English body, the page date.
    $this->withHeaders(['X-API-Key' => 'pk_test_website_pages'])
        ->getJson($this->endpoint.'?locale=en')
        ->assertSuccessful()
        ->assertJsonPath('data.terms.body', '<p>English terms</p>')
        ->assertJsonPath('data.terms.last_update', '2026-05-05');

    // Japanese: Japanese body, SAME date (last_update is not translatable).
    $this->withHeaders(['X-API-Key' => 'pk_test_website_pages'])
        ->getJson($this->endpoint.'?locale=ja')
        ->assertSuccessful()
        ->assertJsonPath('data.terms.body', '<p>日本語</p>')
        ->assertJsonPath('data.terms.last_update', '2026-05-05');

    // Korean: no translation -> body fails open to null, but the date still shows.
    $this->withHeaders(['X-API-Key' => 'pk_test_website_pages'])
        ->getJson($this->endpoint.'?locale=ko')
        ->assertSuccessful()
        ->assertJsonPath('data.terms.body', null)
        ->assertJsonPath('data.terms.last_update', '2026-05-05');
});

test('saving one locale never drops the sibling locales (round-trip)', function () {
    // Guards the admin flow: the editor always PUTs the full body map, so a save
    // while on one locale tab must not wipe the others.
    $page = WebsitePage::factory()->create([
        'project_id' => $this->project->id,
        'key' => 'terms',
        'body' => ['en' => '<p>EN</p>', 'id' => '<p>ID</p>', 'ja' => '<p>JA</p>'],
        'last_updated_at' => '2026-01-01',
    ]);

    // Re-save with an edited Japanese body plus the untouched siblings + a new date.
    $page->setTranslations('body', ['en' => '<p>EN</p>', 'id' => '<p>ID</p>', 'ja' => '<p>JA edited</p>']);
    $page->last_updated_at = '2026-07-07';
    $page->save();

    $this->withHeaders(['X-API-Key' => 'pk_test_website_pages'])
        ->getJson($this->endpoint.'?locale=id')
        ->assertSuccessful()
        ->assertJsonPath('data.terms.body', '<p>ID</p>')
        ->assertJsonPath('data.terms.last_update', '2026-07-07');
});
