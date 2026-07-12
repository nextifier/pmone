<?php

use App\Models\ApiConsumer;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\ResponseCache\Facades\ResponseCache;

uses(RefreshDatabase::class);

beforeEach(function () {
    ResponseCache::clear();

    ApiConsumer::factory()->create(['api_key' => 'pk_test_site_config', 'is_active' => true]);

    $this->project = Project::factory()->create([
        'status' => 'active',
        'username' => 'acme',
    ]);

    $this->endpoint = '/api/public/projects/acme/website-settings';
});

test('site_config is a fail-open empty container for an unconfigured project', function () {
    $this->withHeaders(['X-API-Key' => 'pk_test_site_config'])
        ->getJson($this->endpoint)
        ->assertSuccessful()
        ->assertJsonPath('data.settings.site_config.version', 1)
        ->assertJsonPath('data.settings.site_config.nav', null)
        ->assertJsonPath('data.settings.site_config.analytics', null)
        ->assertJsonPath('data.settings.site_config.appearance', null)
        ->assertJsonPath('data.settings.site_config.identity', null);
});

test('a saved site_config.nav value is returned verbatim', function () {
    $settings = $this->project->settings ?? [];
    data_set($settings, 'website_settings.site_config.nav', [
        'items' => [
            ['label' => 'Home', 'url' => '/'],
            ['label' => 'Tickets', 'url' => '/tickets'],
        ],
    ]);
    $this->project->update(['settings' => $settings]);

    $this->withHeaders(['X-API-Key' => 'pk_test_site_config'])
        ->getJson($this->endpoint)
        ->assertSuccessful()
        ->assertJsonPath('data.settings.site_config.nav.items.0.label', 'Home')
        ->assertJsonPath('data.settings.site_config.nav.items.1.url', '/tickets')
        // Sibling sub-keys remain fail-open null; only nav was configured.
        ->assertJsonPath('data.settings.site_config.analytics', null);
});

test('existing website-settings payload keys are unchanged by the site_config addition', function () {
    $response = $this->withHeaders(['X-API-Key' => 'pk_test_site_config'])
        ->getJson($this->endpoint)
        ->assertSuccessful();

    $response
        ->assertJsonPath('data.settings.rundown.show_search_bar', true)
        ->assertJsonPath('data.settings.brands.show_brand_preview_on_home_page', false)
        ->assertJsonPath('data.settings.partners.show_partners_on_home_page', true)
        ->assertJsonPath('data.settings.hotels.show_hotel_section_on_home_page', false)
        ->assertJsonPath('data.settings.blog.show_post_card_author', false)
        ->assertJsonPath('data.settings.ticket_tabs.show_tickets', true)
        ->assertJsonPath('data.settings.book_space_form.show_brand_name', true)
        ->assertJsonPath('data.settings.terms.last_update', null)
        ->assertJsonPath('data.settings.data_fallback.brands', true)
        ->assertJsonPath('data.settings.og_pages', []);

    expect($response->json('data.settings'))->toHaveKeys([
        'rundown', 'brands', 'partners', 'hotels', 'home_sections', 'blog',
        'ticket_tabs', 'book_space_form', 'terms', 'data_fallback', 'og_pages',
        'site_config',
    ]);
});
