<?php

use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    ApiConsumer::factory()->create([
        'api_key' => 'pk_test_settings_leak',
        'is_active' => true,
    ]);
});

function withInternalSettings(): array
{
    return [
        'contact_form' => [
            'email_config' => [
                'to' => ['staff-internal@panorama.test'],
                'cc' => ['ops-internal@panorama.test'],
                'bcc' => [],
            ],
        ],
        'website_settings' => [
            'hotels' => [
                'notification_email' => 'hotel-ops-internal@panorama.test',
            ],
            'email_subjects' => [
                'confirmation' => 'Internal confirmation subject template',
            ],
        ],
    ];
}

function getPublicProject(string $username): TestResponse
{
    return test()->withHeaders(['X-API-Key' => 'pk_test_settings_leak'])
        ->getJson("/api/public/projects/{$username}");
}

function getPublicEvent(string $username, string $eventSlug): TestResponse
{
    return test()->withHeaders(['X-API-Key' => 'pk_test_settings_leak'])
        ->getJson("/api/public/projects/{$username}/events/{$eventSlug}");
}

function getPublicActiveEvent(string $username): TestResponse
{
    return test()->withHeaders(['X-API-Key' => 'pk_test_settings_leak'])
        ->getJson("/api/public/projects/{$username}/events/active");
}

// The project-profile endpoint is fully unauthenticated (no X-API-Key,
// only throttled), so deliberately send NO api-key header here.
function getProjectProfile(string $username): TestResponse
{
    return test()->getJson("/api/projects/{$username}");
}

test('public project show excludes the raw settings blob entirely', function () {
    $project = Project::factory()->create(['settings' => withInternalSettings()]);

    $response = getPublicProject($project->username)->assertOk();

    expect($response->json('data'))->not->toHaveKey('settings');
    expect($response->getContent())
        ->not->toContain('staff-internal@panorama.test')
        ->not->toContain('ops-internal@panorama.test')
        ->not->toContain('hotel-ops-internal@panorama.test')
        ->not->toContain('email_config')
        ->not->toContain('notification_email')
        ->not->toContain('email_subjects')
        ->not->toContain('Internal confirmation subject template');
});

test('public project show still exposes non-sensitive public fields', function () {
    $project = Project::factory()->create([
        'name' => 'Public Expo',
        'bio' => 'A public bio',
        'settings' => withInternalSettings(),
    ]);

    getPublicProject($project->username)
        ->assertOk()
        ->assertJsonPath('data.name', 'Public Expo')
        ->assertJsonPath('data.username', $project->username)
        ->assertJsonPath('data.bio', 'A public bio')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.visibility', 'public');
});

test('public event detail excludes the raw settings blob entirely', function () {
    $project = Project::factory()->create(['settings' => withInternalSettings()]);
    $event = Event::factory()->published()->create([
        'project_id' => $project->id,
        'settings' => withInternalSettings(),
    ]);

    $response = getPublicEvent($project->username, $event->slug)->assertOk();

    expect($response->json('data'))->not->toHaveKey('settings');
    expect($response->getContent())
        ->not->toContain('staff-internal@panorama.test')
        ->not->toContain('ops-internal@panorama.test')
        ->not->toContain('hotel-ops-internal@panorama.test')
        ->not->toContain('email_config')
        ->not->toContain('notification_email')
        ->not->toContain('email_subjects');
});

test('public active event excludes the raw settings blob entirely', function () {
    $project = Project::factory()->create(['settings' => withInternalSettings()]);
    Event::factory()->published()->create([
        'project_id' => $project->id,
        'is_active' => true,
        'settings' => withInternalSettings(),
    ]);

    $response = getPublicActiveEvent($project->username)->assertOk();

    expect($response->json('data'))->not->toHaveKey('settings');
    expect($response->getContent())
        ->not->toContain('staff-internal@panorama.test')
        ->not->toContain('ops-internal@panorama.test')
        ->not->toContain('hotel-ops-internal@panorama.test')
        ->not->toContain('email_config')
        ->not->toContain('notification_email')
        ->not->toContain('email_subjects');
});

test('public active event still exposes display fields consumed by event websites', function () {
    $project = Project::factory()->create();
    $project->links()->create(['label' => 'Website', 'url' => 'https://example.test']);
    Event::factory()->published()->create([
        'project_id' => $project->id,
        'is_active' => true,
        'location' => 'Some Convention Center, Jakarta',
        'hall' => 'Hall 1',
        'custom_fields' => ['location_short' => 'SCC Jakarta', 'teaser_video_id' => 'abc123'],
    ]);

    getPublicActiveEvent($project->username)
        ->assertOk()
        ->assertJsonPath('data.location_short', 'SCC Jakarta')
        ->assertJsonPath('data.teaser_video_id', 'abc123')
        ->assertJsonPath('data.hall', 'Hall 1')
        ->assertJsonPath('data.website_url', 'https://example.test');
});

test('unauthenticated project-profile endpoint excludes the raw settings blob entirely', function () {
    $project = Project::factory()->create(['settings' => withInternalSettings()]);

    $response = getProjectProfile($project->username)->assertOk();

    expect($response->json('data'))->not->toHaveKey('settings');
    expect($response->getContent())
        ->not->toContain('staff-internal@panorama.test')
        ->not->toContain('ops-internal@panorama.test')
        ->not->toContain('hotel-ops-internal@panorama.test')
        ->not->toContain('email_config')
        ->not->toContain('notification_email')
        ->not->toContain('email_subjects')
        ->not->toContain('Internal confirmation subject template');
});

test('unauthenticated project-profile endpoint still exposes non-sensitive public fields', function () {
    $project = Project::factory()->create([
        'name' => 'Public Expo',
        'settings' => withInternalSettings(),
    ]);
    $project->links()->create(['label' => 'Website', 'url' => 'https://example.test']);

    getProjectProfile($project->username)
        ->assertOk()
        ->assertJsonPath('data.name', 'Public Expo')
        ->assertJsonPath('data.username', $project->username)
        ->assertJsonPath('data.links.0.url', 'https://example.test');
});
