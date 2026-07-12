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
