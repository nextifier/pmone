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
