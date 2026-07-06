<?php

use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\Project;
use App\Models\RundownItem;
use App\Models\User;
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

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');

    $this->project = Project::factory()->create([
        'settings' => [
            'contact_form' => ['enabled' => true],
            'website_settings' => [
                'rundown' => [
                    'show_search_bar' => true,
                    'show_all_rundown_details' => false,
                ],
            ],
        ],
    ]);

    $this->endpoint = "/api/projects/{$this->project->username}/website-settings";
});

it('updates website rundown settings and merges into existing settings', function () {
    $this->actingAs($this->user);

    $response = $this->patchJson($this->endpoint, [
        'rundown' => [
            'show_search_bar' => false,
            'show_all_rundown_details' => true,
        ],
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.website_settings.rundown.show_search_bar', false)
        ->assertJsonPath('data.website_settings.rundown.show_all_rundown_details', true);

    $this->project->refresh();

    expect(data_get($this->project->settings, 'website_settings.rundown.show_search_bar'))->toBeFalse();
    expect(data_get($this->project->settings, 'website_settings.rundown.show_all_rundown_details'))->toBeTrue();
    // Confirm unrelated settings remain intact
    expect(data_get($this->project->settings, 'contact_form.enabled'))->toBeTrue();
});

it('preserves website_settings when the general project update sends only contact_form', function () {
    $this->actingAs($this->user);

    // The admin edit form (FormProject) submits a settings payload carrying
    // only contact_form. ProjectController@update must merge it into the
    // existing settings column, not overwrite it - otherwise website_settings
    // (and email_subjects/hotels) would be silently dropped.
    $response = $this->putJson("/api/projects/{$this->project->username}", [
        'settings' => ['contact_form' => ['enabled' => false]],
    ]);

    $response->assertSuccessful();

    $this->project->refresh();
    // contact_form was replaced with the submitted block...
    expect(data_get($this->project->settings, 'contact_form.enabled'))->toBeFalse();
    // ...while the untouched website_settings block survives (no data loss).
    expect(data_get($this->project->settings, 'website_settings.rundown.show_search_bar'))->toBeTrue();
    expect(data_get($this->project->settings, 'website_settings.rundown.show_all_rundown_details'))->toBeFalse();
});

it('persists show_rundown_on_home_page flag', function () {
    $this->actingAs($this->user);

    $response = $this->patchJson($this->endpoint, [
        'rundown' => [
            'show_rundown_on_home_page' => true,
        ],
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.website_settings.rundown.show_rundown_on_home_page', true);

    $this->project->refresh();
    expect(data_get($this->project->settings, 'website_settings.rundown.show_rundown_on_home_page'))->toBeTrue();
});

it('exposes show_rundown_on_home_page in public rundown response', function () {
    $apiConsumer = ApiConsumer::factory()->create([
        'api_key' => 'pk_test_home_flag',
        'is_active' => true,
    ]);

    $event = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'start_date' => '2026-07-22 09:00:00',
        'end_date' => '2026-07-23 18:00:00',
    ]);

    RundownItem::factory()->onDate('2026-07-22')->create([
        'event_id' => $event->id,
        'title' => ['en' => 'Session'],
        'is_active' => true,
    ]);

    $publicEndpoint = "/api/public/projects/{$this->project->username}/events/{$event->slug}/rundown";

    $this->withHeaders(['X-API-Key' => 'pk_test_home_flag'])
        ->getJson($publicEndpoint)
        ->assertJsonPath('data.settings.show_rundown_on_home_page', false);

    $this->actingAs($this->user);
    $this->patchJson($this->endpoint, [
        'rundown' => ['show_rundown_on_home_page' => true],
    ])->assertSuccessful();

    $this->withHeaders(['X-API-Key' => 'pk_test_home_flag'])
        ->getJson($publicEndpoint)
        ->assertJsonPath('data.settings.show_rundown_on_home_page', true);
});

it('partially merges only the provided fields', function () {
    $this->actingAs($this->user);

    $response = $this->patchJson($this->endpoint, [
        'rundown' => [
            'show_search_bar' => false,
        ],
    ]);

    $response->assertSuccessful();

    $this->project->refresh();
    expect(data_get($this->project->settings, 'website_settings.rundown.show_search_bar'))->toBeFalse();
    expect(data_get($this->project->settings, 'website_settings.rundown.show_all_rundown_details'))->toBeFalse();
});

it('rejects unauthenticated requests', function () {
    $response = $this->patchJson($this->endpoint, [
        'rundown' => ['show_search_bar' => false],
    ]);

    $response->assertUnauthorized();
});

it('blocks non-member users from updating website settings', function () {
    $outsider = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($outsider);

    $response = $this->patchJson($this->endpoint, [
        'rundown' => ['show_search_bar' => false],
    ]);

    $response->assertForbidden();
});

it('clears public rundown cache when settings change', function () {
    $apiConsumer = ApiConsumer::factory()->create([
        'api_key' => 'pk_test_settings',
        'is_active' => true,
    ]);

    $event = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'start_date' => '2026-07-22 09:00:00',
        'end_date' => '2026-07-23 18:00:00',
    ]);

    RundownItem::factory()->onDate('2026-07-22')->create([
        'event_id' => $event->id,
        'title' => ['en' => 'Session'],
        'is_active' => true,
    ]);

    $publicEndpoint = "/api/public/projects/{$this->project->username}/events/{$event->slug}/rundown";

    // Warm cache
    $this->withHeaders(['X-API-Key' => 'pk_test_settings'])
        ->getJson($publicEndpoint)
        ->assertJsonPath('data.settings.show_search_bar', true);

    // Patch settings as member
    $this->actingAs($this->user);
    $this->patchJson($this->endpoint, [
        'rundown' => ['show_search_bar' => false],
    ])->assertSuccessful();

    // Public response reflects the new value (cache must have been cleared)
    $this->withHeaders(['X-API-Key' => 'pk_test_settings'])
        ->getJson($publicEndpoint)
        ->assertJsonPath('data.settings.show_search_bar', false);
});

it('persists show_partners_on_home_page flag', function () {
    $this->actingAs($this->user);

    $response = $this->patchJson($this->endpoint, [
        'partners' => [
            'show_partners_on_home_page' => false,
        ],
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.website_settings.partners.show_partners_on_home_page', false);

    $this->project->refresh();
    expect(data_get($this->project->settings, 'website_settings.partners.show_partners_on_home_page'))->toBeFalse();
    // Confirm unrelated settings remain intact
    expect(data_get($this->project->settings, 'website_settings.rundown.show_search_bar'))->toBeTrue();
});

it('exposes show_partners_on_home_page (default true) in public website-settings response', function () {
    ApiConsumer::factory()->create([
        'api_key' => 'pk_test_partners_flag',
        'is_active' => true,
    ]);

    $publicEndpoint = "/api/public/projects/{$this->project->username}/website-settings";

    // Unconfigured project keeps the historical always-show behaviour.
    $this->withHeaders(['X-API-Key' => 'pk_test_partners_flag'])
        ->getJson($publicEndpoint)
        ->assertJsonPath('data.settings.partners.show_partners_on_home_page', true);

    $this->actingAs($this->user);
    $this->patchJson($this->endpoint, [
        'partners' => ['show_partners_on_home_page' => false],
    ])->assertSuccessful();

    $this->withHeaders(['X-API-Key' => 'pk_test_partners_flag'])
        ->getJson($publicEndpoint)
        ->assertJsonPath('data.settings.partners.show_partners_on_home_page', false);
});

it('persists show_hotel_section_on_home_page flag', function () {
    $this->actingAs($this->user);

    $response = $this->patchJson($this->endpoint, [
        'hotels' => [
            'show_hotel_section_on_home_page' => true,
        ],
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.website_settings.hotels.show_hotel_section_on_home_page', true);

    $this->project->refresh();
    expect(data_get($this->project->settings, 'website_settings.hotels.show_hotel_section_on_home_page'))->toBeTrue();
    // Confirm unrelated settings remain intact
    expect(data_get($this->project->settings, 'website_settings.rundown.show_search_bar'))->toBeTrue();
});

it('exposes show_hotel_section_on_home_page in public website-settings response', function () {
    ApiConsumer::factory()->create([
        'api_key' => 'pk_test_hotel_flag',
        'is_active' => true,
    ]);

    $publicEndpoint = "/api/public/projects/{$this->project->username}/website-settings";

    $this->withHeaders(['X-API-Key' => 'pk_test_hotel_flag'])
        ->getJson($publicEndpoint)
        ->assertJsonPath('data.settings.hotels.show_hotel_section_on_home_page', false);

    $this->actingAs($this->user);
    $this->patchJson($this->endpoint, [
        'hotels' => ['show_hotel_section_on_home_page' => true],
    ])->assertSuccessful();

    $this->withHeaders(['X-API-Key' => 'pk_test_hotel_flag'])
        ->getJson($publicEndpoint)
        ->assertJsonPath('data.settings.hotels.show_hotel_section_on_home_page', true);
});

it('persists hotel notification email config', function () {
    $this->actingAs($this->user);

    $response = $this->patchJson($this->endpoint, [
        'hotels' => [
            'notification_email' => [
                'to' => ['staff@example.com', 'manager@example.com'],
                'cc' => ['cc@example.com'],
                'bcc' => [],
            ],
        ],
        'email_subjects' => [
            'staff_confirmed' => 'Booking {status}: {reservation_number}',
        ],
    ]);

    $response->assertSuccessful();

    $this->project->refresh();
    $config = data_get($this->project->settings, 'website_settings.hotels.notification_email');
    $subjects = data_get($this->project->settings, 'website_settings.email_subjects');

    expect($config['to'])->toBe(['staff@example.com', 'manager@example.com']);
    expect($config['cc'])->toBe(['cc@example.com']);
    expect($subjects['staff_confirmed'])->toBe('Booking {status}: {reservation_number}');
});

it('replaces hotel notification recipients wholesale instead of merging by index', function () {
    $this->actingAs($this->user);

    $this->patchJson($this->endpoint, [
        'hotels' => [
            'notification_email' => [
                'to' => ['first@example.com', 'second@example.com'],
                'cc' => [],
                'bcc' => [],
                'subject' => '',
            ],
        ],
    ])->assertSuccessful();

    // Removing the second recipient must not be resurrected by a recursive merge.
    $this->patchJson($this->endpoint, [
        'hotels' => [
            'notification_email' => [
                'to' => ['first@example.com'],
                'cc' => [],
                'bcc' => [],
                'subject' => '',
            ],
        ],
    ])->assertSuccessful();

    $this->project->refresh();

    expect(data_get($this->project->settings, 'website_settings.hotels.notification_email.to'))
        ->toBe(['first@example.com']);
});

it('rejects invalid hotel notification recipient emails', function () {
    $this->actingAs($this->user);

    $this->patchJson($this->endpoint, [
        'hotels' => [
            'notification_email' => [
                'to' => ['not-an-email'],
            ],
        ],
    ])->assertUnprocessable();
});

it('persists the foreign-currency estimate settings', function () {
    $this->actingAs($this->user);

    $response = $this->patchJson($this->endpoint, [
        'hotels' => [
            'show_estimated_price_in_foreign_currency' => true,
            'estimated_price_currency' => 'USD',
        ],
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.website_settings.hotels.show_estimated_price_in_foreign_currency', true)
        ->assertJsonPath('data.website_settings.hotels.estimated_price_currency', 'USD');

    $this->project->refresh();
    expect(data_get($this->project->settings, 'website_settings.hotels.show_estimated_price_in_foreign_currency'))->toBeTrue();
    expect(data_get($this->project->settings, 'website_settings.hotels.estimated_price_currency'))->toBe('USD');
});

it('rejects an unknown estimated-price currency code', function () {
    $this->actingAs($this->user);

    $this->patchJson($this->endpoint, [
        'hotels' => [
            'estimated_price_currency' => 'ZZZ',
        ],
    ])->assertUnprocessable();
});

it('rejects IDR as the estimated-price currency', function () {
    $this->actingAs($this->user);

    $this->patchJson($this->endpoint, [
        'hotels' => [
            'estimated_price_currency' => 'IDR',
        ],
    ])->assertUnprocessable();
});

it('persists website display settings (blog, ticket tabs, book space form, terms)', function () {
    $this->actingAs($this->user);

    $response = $this->patchJson($this->endpoint, [
        'blog' => ['show_post_card_author' => true],
        'ticket_tabs' => ['show_photos' => false, 'show_guests' => true],
        'book_space_form' => ['show_job_title' => true],
        'terms' => ['last_update' => '2026-04-30'],
    ]);

    $response->assertSuccessful();

    $this->project->refresh();
    $ws = data_get($this->project->settings, 'website_settings');

    expect(data_get($ws, 'blog.show_post_card_author'))->toBeTrue();
    expect(data_get($ws, 'ticket_tabs.show_photos'))->toBeFalse();
    expect(data_get($ws, 'ticket_tabs.show_guests'))->toBeTrue();
    expect(data_get($ws, 'book_space_form.show_job_title'))->toBeTrue();
    expect(data_get($ws, 'terms.last_update'))->toBe('2026-04-30');
    // Existing rundown block is untouched.
    expect(data_get($ws, 'rundown.show_search_bar'))->toBeTrue();
});

it('persists per-section data_fallback flags', function () {
    $this->actingAs($this->user);

    $response = $this->patchJson($this->endpoint, [
        'data_fallback' => [
            'brands' => false,
            'guests' => false,
        ],
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.website_settings.data_fallback.brands', false)
        ->assertJsonPath('data.website_settings.data_fallback.guests', false);

    $this->project->refresh();
    expect(data_get($this->project->settings, 'website_settings.data_fallback.brands'))->toBeFalse();
    expect(data_get($this->project->settings, 'website_settings.data_fallback.guests'))->toBeFalse();
    // Unrelated settings remain intact.
    expect(data_get($this->project->settings, 'website_settings.rundown.show_search_bar'))->toBeTrue();
});

it('rejects a non-boolean data_fallback value', function () {
    $this->actingAs($this->user);

    $this->patchJson($this->endpoint, [
        'data_fallback' => ['brands' => 'maybe'],
    ])->assertUnprocessable();
});

it('exposes data_fallback flags (default true) in public website-settings response', function () {
    ApiConsumer::factory()->create([
        'api_key' => 'pk_test_fallback_flag',
        'is_active' => true,
    ]);

    $publicEndpoint = "/api/public/projects/{$this->project->username}/website-settings";

    // Unconfigured project keeps the historical always-fallback behaviour.
    $this->withHeaders(['X-API-Key' => 'pk_test_fallback_flag'])
        ->getJson($publicEndpoint)
        ->assertJsonPath('data.settings.data_fallback.brands', true)
        ->assertJsonPath('data.settings.data_fallback.guests', true)
        ->assertJsonPath('data.settings.data_fallback.media_coverages', true);

    $this->actingAs($this->user);
    $this->patchJson($this->endpoint, [
        'data_fallback' => ['brands' => false],
    ])->assertSuccessful();

    $this->withHeaders(['X-API-Key' => 'pk_test_fallback_flag'])
        ->getJson($publicEndpoint)
        ->assertJsonPath('data.settings.data_fallback.brands', false)
        ->assertJsonPath('data.settings.data_fallback.guests', true);
});

it('exposes website display settings with base defaults in the public response', function () {
    ApiConsumer::factory()->create([
        'api_key' => 'pk_test_display',
        'is_active' => true,
    ]);

    $publicEndpoint = "/api/public/projects/{$this->project->username}/website-settings";

    // Untouched project returns the base app.config defaults.
    $this->withHeaders(['X-API-Key' => 'pk_test_display'])
        ->getJson($publicEndpoint)
        ->assertJsonPath('data.settings.blog.show_post_card_author', false)
        ->assertJsonPath('data.settings.ticket_tabs.show_tickets', true)
        ->assertJsonPath('data.settings.ticket_tabs.show_guests', false)
        ->assertJsonPath('data.settings.book_space_form.show_brand_name', true)
        ->assertJsonPath('data.settings.book_space_form.show_job_title', false)
        ->assertJsonPath('data.settings.terms.last_update', null);

    $this->actingAs($this->user);
    $this->patchJson($this->endpoint, [
        'blog' => ['show_post_card_author' => true],
        'terms' => ['last_update' => '2025-12-30'],
    ])->assertSuccessful();

    $this->withHeaders(['X-API-Key' => 'pk_test_display'])
        ->getJson($publicEndpoint)
        ->assertJsonPath('data.settings.blog.show_post_card_author', true)
        ->assertJsonPath('data.settings.terms.last_update', '2025-12-30');
});

it('persists the home_sections visibility map and preserves other settings', function () {
    $this->actingAs($this->user);

    $response = $this->patchJson($this->endpoint, [
        'home_sections' => [
            'rundown' => true,
            'about_event' => false,
        ],
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.website_settings.home_sections.rundown', true)
        ->assertJsonPath('data.website_settings.home_sections.about_event', false);

    $this->project->refresh();
    expect(data_get($this->project->settings, 'website_settings.home_sections.rundown'))->toBeTrue();
    expect(data_get($this->project->settings, 'website_settings.home_sections.about_event'))->toBeFalse();
    // Unrelated settings remain intact.
    expect(data_get($this->project->settings, 'website_settings.rundown.show_search_bar'))->toBeTrue();
});

it('rejects a non-boolean home_sections value', function () {
    $this->actingAs($this->user);

    $this->patchJson($this->endpoint, [
        'home_sections' => ['rundown' => 'maybe'],
    ])->assertUnprocessable();
});

it('rejects an unknown home_sections key', function () {
    $this->actingAs($this->user);

    $this->patchJson($this->endpoint, [
        'home_sections' => ['not_a_real_section' => true],
    ])->assertUnprocessable();
});

it('exposes the home_sections map with catalog defaults in the public response', function () {
    ApiConsumer::factory()->create([
        'api_key' => 'pk_test_home_sections',
        'is_active' => true,
    ]);

    $publicEndpoint = "/api/public/projects/{$this->project->username}/website-settings";

    // Unconfigured project reflects the catalog defaults: the four original
    // toggles keep their historical defaults, new sections default to visible.
    $this->withHeaders(['X-API-Key' => 'pk_test_home_sections'])
        ->getJson($publicEndpoint)
        ->assertJsonPath('data.settings.home_sections.rundown', false)
        ->assertJsonPath('data.settings.home_sections.brand_preview', false)
        ->assertJsonPath('data.settings.home_sections.hotels', false)
        ->assertJsonPath('data.settings.home_sections.partners', true)
        ->assertJsonPath('data.settings.home_sections.hero', true)
        ->assertJsonPath('data.settings.home_sections.about_event', true);
});

it('derives the legacy nested visibility keys from home_sections', function () {
    ApiConsumer::factory()->create([
        'api_key' => 'pk_test_home_derive',
        'is_active' => true,
    ]);

    $publicEndpoint = "/api/public/projects/{$this->project->username}/website-settings";

    $this->actingAs($this->user);
    $this->patchJson($this->endpoint, [
        'home_sections' => [
            'rundown' => true,
            'brand_preview' => true,
            'partners' => false,
            'hotels' => true,
        ],
    ])->assertSuccessful();

    // Deployed event sites read the nested shape; it must agree with the map.
    $this->withHeaders(['X-API-Key' => 'pk_test_home_derive'])
        ->getJson($publicEndpoint)
        ->assertJsonPath('data.settings.home_sections.rundown', true)
        ->assertJsonPath('data.settings.rundown.show_rundown_on_home_page', true)
        ->assertJsonPath('data.settings.brands.show_brand_preview_on_home_page', true)
        ->assertJsonPath('data.settings.partners.show_partners_on_home_page', false)
        ->assertJsonPath('data.settings.hotels.show_hotel_section_on_home_page', true);
});

it('falls back to a legacy stored visibility flag when home_sections is absent', function () {
    ApiConsumer::factory()->create([
        'api_key' => 'pk_test_home_legacy',
        'is_active' => true,
    ]);

    // Simulate a project configured through the old UI: value stored at the
    // legacy nested path, with no home_sections map yet.
    $settings = $this->project->settings;
    data_set($settings, 'website_settings.rundown.show_rundown_on_home_page', true);
    $this->project->update(['settings' => $settings]);

    $publicEndpoint = "/api/public/projects/{$this->project->username}/website-settings";

    $this->withHeaders(['X-API-Key' => 'pk_test_home_legacy'])
        ->getJson($publicEndpoint)
        ->assertJsonPath('data.settings.home_sections.rundown', true)
        ->assertJsonPath('data.settings.rundown.show_rundown_on_home_page', true);
});

it('exposes the home sections catalog and resolved values to the admin', function () {
    $this->actingAs($this->user);

    $this->patchJson($this->endpoint, [
        'home_sections' => ['about_event' => false],
    ])->assertSuccessful();

    $response = $this->getJson("/api/projects/{$this->project->username}");

    $response->assertSuccessful()
        ->assertJsonPath('data.home_sections.about_event', false)
        ->assertJsonPath('data.home_sections.partners', true);

    $catalog = $response->json('data.home_sections_catalog');
    expect($catalog)->toBeArray()->not->toBeEmpty();
    expect($catalog[0])->toHaveKeys(['key', 'label', 'default']);
});
