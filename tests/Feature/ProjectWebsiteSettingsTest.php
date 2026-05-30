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
