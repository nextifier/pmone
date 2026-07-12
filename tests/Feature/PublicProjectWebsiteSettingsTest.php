<?php

use App\Models\ApiConsumer;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
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
    $this->writeEndpoint = '/api/projects/acme/website-settings';

    // Admin auth for the write endpoint (plan 008 nav save tests). Mirrors
    // ProjectWebsiteSettingsTest.php's beforeEach.
    $masterRole = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'projects.update', 'guard_name' => 'web']);
    $masterRole->syncPermissions(Permission::all());
    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole('master');
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

// --- Plan 008: nav save / wholesale-replace / fail-open ------------------

test('saving site_config.nav returns it verbatim in the public payload', function () {
    $nav = [
        'header' => [
            ['label' => 'Home', 'path' => '/'],
            ['label' => 'Tickets', 'path' => '/tickets'],
            [
                'label' => 'About',
                'links' => [
                    ['label' => 'Programs', 'path' => '/programs'],
                    ['label' => 'Guests', 'path' => '/guests'],
                ],
            ],
        ],
        'dialog' => [
            ['label' => 'Home', 'path' => '/'],
        ],
        'footer' => [
            ['label' => 'Contact', 'path' => '#contact'],
            ['label' => 'Register', 'path' => 'https://register.example.com'],
        ],
    ];

    $this->actingAs($this->admin)
        ->patchJson($this->writeEndpoint, ['site_config' => ['nav' => $nav]])
        ->assertSuccessful();

    $this->withHeaders(['X-API-Key' => 'pk_test_site_config'])
        ->getJson($this->endpoint)
        ->assertSuccessful()
        ->assertJsonPath('data.settings.site_config.nav.header.0.label', 'Home')
        ->assertJsonPath('data.settings.site_config.nav.header.1.path', '/tickets')
        ->assertJsonPath('data.settings.site_config.nav.header.2.links.0.label', 'Programs')
        ->assertJsonPath('data.settings.site_config.nav.dialog.0.label', 'Home')
        ->assertJsonPath('data.settings.site_config.nav.footer.0.path', '#contact')
        ->assertJsonPath('data.settings.site_config.nav.footer.1.path', 'https://register.example.com');
});

test('saving a shorter nav.header array wholesale-replaces instead of resurrecting trailing items', function () {
    $this->actingAs($this->admin)->patchJson($this->writeEndpoint, [
        'site_config' => [
            'nav' => [
                'header' => [
                    ['label' => 'Home', 'path' => '/'],
                    ['label' => 'Tickets', 'path' => '/tickets'],
                    ['label' => 'Contact', 'path' => '/contact'],
                ],
                'dialog' => [],
                'footer' => [],
            ],
        ],
    ])->assertSuccessful();

    // Save again with only 2 items - the 3rd ("Contact") must not survive via
    // array_replace_recursive's index-based list merge.
    $this->actingAs($this->admin)->patchJson($this->writeEndpoint, [
        'site_config' => [
            'nav' => [
                'header' => [
                    ['label' => 'Home', 'path' => '/'],
                    ['label' => 'Tickets', 'path' => '/tickets'],
                ],
                'dialog' => [],
                'footer' => [],
            ],
        ],
    ])->assertSuccessful();

    $this->project->refresh();
    $header = data_get($this->project->settings, 'website_settings.site_config.nav.header');

    expect($header)->toHaveCount(2);
    expect(collect($header)->pluck('label')->all())->toBe(['Home', 'Tickets']);

    $this->withHeaders(['X-API-Key' => 'pk_test_site_config'])
        ->getJson($this->endpoint)
        ->assertSuccessful()
        ->assertJsonCount(2, 'data.settings.site_config.nav.header');
});

test('site_config.nav stays fail-open null when a write touches other website settings but not nav', function () {
    $this->actingAs($this->admin)->patchJson($this->writeEndpoint, [
        'rundown' => ['show_search_bar' => false],
    ])->assertSuccessful();

    $this->withHeaders(['X-API-Key' => 'pk_test_site_config'])
        ->getJson($this->endpoint)
        ->assertSuccessful()
        ->assertJsonPath('data.settings.rundown.show_search_bar', false)
        ->assertJsonPath('data.settings.site_config.nav', null);
});

test('rejects a nav item with neither a valid path nor a links group', function () {
    $this->actingAs($this->admin)->patchJson($this->writeEndpoint, [
        'site_config' => [
            'nav' => [
                'header' => [
                    ['label' => 'Broken', 'path' => 'not-a-valid-path'],
                ],
            ],
        ],
    ])->assertUnprocessable();
});

// --- Plan 009: analytics ids save / validate / fail-open -----------------

test('saving site_config.analytics returns it verbatim in the public payload', function () {
    $this->actingAs($this->admin)
        ->patchJson($this->writeEndpoint, [
            'site_config' => [
                'analytics' => [
                    'ga4' => 'G-ABC1234567',
                    'tiktok_pixel' => 'CQWERTY1234567890',
                ],
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.website_settings.site_config.analytics.ga4', 'G-ABC1234567')
        ->assertJsonPath('data.website_settings.site_config.analytics.tiktok_pixel', 'CQWERTY1234567890');

    $this->withHeaders(['X-API-Key' => 'pk_test_site_config'])
        ->getJson($this->endpoint)
        ->assertSuccessful()
        ->assertJsonPath('data.settings.site_config.analytics.ga4', 'G-ABC1234567')
        ->assertJsonPath('data.settings.site_config.analytics.tiktok_pixel', 'CQWERTY1234567890');
});

test('rejects a malformed GA4 measurement id', function () {
    $this->actingAs($this->admin)->patchJson($this->writeEndpoint, [
        'site_config' => [
            'analytics' => [
                'ga4' => 'not-a-valid-ga4-id',
            ],
        ],
    ])->assertUnprocessable();
});

test('accepts a null analytics id to clear it', function () {
    $this->actingAs($this->admin)->patchJson($this->writeEndpoint, [
        'site_config' => [
            'analytics' => [
                'ga4' => 'G-ABC1234567',
                'tiktok_pixel' => 'CQWERTY1234567890',
            ],
        ],
    ])->assertSuccessful();

    $this->actingAs($this->admin)->patchJson($this->writeEndpoint, [
        'site_config' => [
            'analytics' => [
                'ga4' => null,
                'tiktok_pixel' => null,
            ],
        ],
    ])->assertSuccessful();

    $this->project->refresh();
    expect(data_get($this->project->settings, 'website_settings.site_config.analytics.ga4'))->toBeNull();
    expect(data_get($this->project->settings, 'website_settings.site_config.analytics.tiktok_pixel'))->toBeNull();
});

test('site_config.analytics stays fail-open null when a write touches other website settings but not analytics', function () {
    $this->actingAs($this->admin)->patchJson($this->writeEndpoint, [
        'rundown' => ['show_search_bar' => false],
    ])->assertSuccessful();

    $this->withHeaders(['X-API-Key' => 'pk_test_site_config'])
        ->getJson($this->endpoint)
        ->assertSuccessful()
        ->assertJsonPath('data.settings.rundown.show_search_bar', false)
        ->assertJsonPath('data.settings.site_config.analytics', null);
});

// --- Plan 010: appearance tokens save / validate / fail-open -------------

test('saving site_config.appearance returns it verbatim in the public payload', function () {
    $this->actingAs($this->admin)
        ->patchJson($this->writeEndpoint, [
            'site_config' => [
                'appearance' => [
                    'enabled' => true,
                    'baseColor' => 'zinc',
                    'theme' => 'blue',
                    'chartColor' => 'blue',
                    'radius' => 'small',
                ],
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.website_settings.site_config.appearance.enabled', true)
        ->assertJsonPath('data.website_settings.site_config.appearance.baseColor', 'zinc')
        ->assertJsonPath('data.website_settings.site_config.appearance.theme', 'blue')
        ->assertJsonPath('data.website_settings.site_config.appearance.chartColor', 'blue')
        ->assertJsonPath('data.website_settings.site_config.appearance.radius', 'small');

    $this->withHeaders(['X-API-Key' => 'pk_test_site_config'])
        ->getJson($this->endpoint)
        ->assertSuccessful()
        ->assertJsonPath('data.settings.site_config.appearance.enabled', true)
        ->assertJsonPath('data.settings.site_config.appearance.baseColor', 'zinc')
        ->assertJsonPath('data.settings.site_config.appearance.theme', 'blue')
        ->assertJsonPath('data.settings.site_config.appearance.chartColor', 'blue')
        ->assertJsonPath('data.settings.site_config.appearance.radius', 'small');
});

test('rejects a baseColor outside the curated appearance palette', function () {
    $this->actingAs($this->admin)->patchJson($this->writeEndpoint, [
        'site_config' => [
            'appearance' => [
                'baseColor' => 'crimson',
            ],
        ],
    ])->assertUnprocessable();
});

test('rejects a theme outside the curated appearance palette', function () {
    $this->actingAs($this->admin)->patchJson($this->writeEndpoint, [
        'site_config' => [
            'appearance' => [
                'theme' => 'not-a-real-theme',
            ],
        ],
    ])->assertUnprocessable();
});

test('rejects a radius outside the allowed set', function () {
    $this->actingAs($this->admin)->patchJson($this->writeEndpoint, [
        'site_config' => [
            'appearance' => [
                'radius' => 'huge',
            ],
        ],
    ])->assertUnprocessable();
});

test('site_config.appearance stays fail-open null when a write touches other website settings but not appearance', function () {
    $this->actingAs($this->admin)->patchJson($this->writeEndpoint, [
        'rundown' => ['show_search_bar' => false],
    ])->assertSuccessful();

    $this->withHeaders(['X-API-Key' => 'pk_test_site_config'])
        ->getJson($this->endpoint)
        ->assertSuccessful()
        ->assertJsonPath('data.settings.rundown.show_search_bar', false)
        ->assertJsonPath('data.settings.site_config.appearance', null);
});
