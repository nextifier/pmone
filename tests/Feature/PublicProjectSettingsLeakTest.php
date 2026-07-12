<?php

use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;

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

// Regression coverage for the shared-route hotfix: `GET /api/projects/{username}`
// is the ONLY GET handler for that URI (Laravel keeps just the last-registered
// route for a given method+URI, so ProjectController::show is unreachable and
// this same ProfileController::getProjectProfile action also serves the
// authenticated admin app, e.g. the Website Settings page). Authenticated,
// authorized callers must get the full settings blob back; anonymous callers
// must never see it, regardless of the project's visibility.
test('authenticated authorized admin gets the full settings blob from the project-profile endpoint', function () {
    Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);

    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('master');

    $project = Project::factory()->create([
        'name' => 'Public Expo',
        'settings' => withInternalSettings(),
    ]);
    $project->links()->create(['label' => 'Website', 'url' => 'https://example.test']);

    $response = test()->actingAs($admin)
        ->getJson("/api/projects/{$project->username}")
        ->assertOk();

    expect($response->json('data'))->toHaveKey('settings');
    $response
        ->assertJsonPath('data.settings.website_settings.hotels.notification_email', 'hotel-ops-internal@panorama.test')
        ->assertJsonPath('data.settings.contact_form.email_config.to.0', 'staff-internal@panorama.test');
});

test('authenticated authorized admin still gets the fields the admin app relies on', function () {
    Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);

    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('master');

    $project = Project::factory()->create([
        'name' => 'Public Expo',
        'settings' => withInternalSettings(),
    ]);
    $project->links()->create(['label' => 'Website', 'url' => 'https://example.test']);

    test()->actingAs($admin)
        ->getJson("/api/projects/{$project->username}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Public Expo')
        ->assertJsonPath('data.username', $project->username)
        ->assertJsonPath('data.links.0.url', 'https://example.test')
        ->assertJsonStructure([
            'data' => ['home_sections', 'home_sections_catalog'],
        ]);
});

test('authenticated but unauthorized caller does not get the settings blob from the project-profile endpoint', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    // Private project the user has no membership on: ProjectPolicy::view
    // denies non-members, so this exercises the 403 path and confirms it
    // never leaks settings on the way there.
    $project = Project::factory()->create([
        'visibility' => 'private',
        'settings' => withInternalSettings(),
    ]);

    $response = test()->actingAs($user)->getJson("/api/projects/{$project->username}");

    $response->assertForbidden();
    expect($response->getContent())->not->toContain('staff-internal@panorama.test');
});
