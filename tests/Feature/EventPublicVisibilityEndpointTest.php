<?php

use App\Jobs\PurgeEdgeCache;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RoleAndPermissionSeeder']);

    $this->project = Project::factory()->create();
    $this->event = Event::factory()->published()->create([
        'project_id' => $this->project->id,
    ]);

    $this->endpoint = "/api/events/{$this->event->id}/public-visibility";
});

function visibilityMaster(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('master');

    return $user;
}

test('show returns both flags', function () {
    $this->actingAs(visibilityMaster())
        ->getJson($this->endpoint)
        ->assertOk()
        ->assertExactJson(['data' => [
            'brands_public_visible' => true,
            'rundown_public_visible' => true,
        ]]);
});

test('update writes both flags in one request', function () {
    $this->actingAs(visibilityMaster())
        ->putJson($this->endpoint, [
            'brands_public_visible' => false,
            'rundown_public_visible' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.brands_public_visible', false)
        ->assertJsonPath('data.rundown_public_visible', false);

    expect($this->event->fresh())
        ->brands_public_visible->toBeFalse()
        ->rundown_public_visible->toBeFalse();
});

test('a partial payload leaves the other flag alone', function () {
    $this->event->update(['brands_public_visible' => false, 'rundown_public_visible' => false]);

    $this->actingAs(visibilityMaster())
        ->putJson($this->endpoint, ['brands_public_visible' => true])
        ->assertOk()
        ->assertJsonPath('data.brands_public_visible', true)
        ->assertJsonPath('data.rundown_public_visible', false);
});

test('the flip is recorded in the activity log', function () {
    $this->actingAs(visibilityMaster())
        ->putJson($this->endpoint, ['brands_public_visible' => false])
        ->assertOk();

    expect($this->event->activities()->latest('id')->first()?->properties['attributes'] ?? [])
        ->toHaveKey('brands_public_visible');
});

test('rejects a user without events.update', function () {
    $this->actingAs(securityTestUser())
        ->putJson($this->endpoint, ['brands_public_visible' => false])
        ->assertForbidden();
});

test('rejects a permission holder who is not a member of the project', function () {
    $this->actingAs(securityTestUser(['events.read', 'events.update']))
        ->putJson($this->endpoint, ['brands_public_visible' => false])
        ->assertForbidden();
});

test('requires authentication', function () {
    $this->putJson($this->endpoint, ['brands_public_visible' => false])
        ->assertUnauthorized();
});

test('rejects a non-boolean payload', function () {
    $this->actingAs(visibilityMaster())
        ->putJson($this->endpoint, ['brands_public_visible' => 'maybe'])
        ->assertJsonValidationErrors('brands_public_visible');
});

/*
 * The `brands` edge tag maps to the /brands LISTING only
 * (config/edge-sites.php), and Event implements no edgeCachePaths(), so brand
 * DETAIL pages would otherwise keep serving from Cloudflare for up to seven
 * days after the list was hidden. These two cover the targeted purge that
 * closes that hole - and nothing else does, because the surrounding
 * ResponseCache spy in other suites replaces the binding that reaches it.
 */

test('hiding brands queues a targeted edge purge for every brand detail page', function () {
    config(['edge-sites.token' => 'token-abc']);

    $brand = Brand::factory()->create();
    BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $this->event->id,
        'status' => 'active',
    ]);

    // Faked only now: creating the BrandEvent above fires its own
    // ClearsResponseCache purge, which would otherwise land in these assertions.
    Queue::fake();

    $this->actingAs(visibilityMaster())
        ->putJson($this->endpoint, ['brands_public_visible' => false])
        ->assertOk();

    Queue::assertPushed(
        PurgeEdgeCache::class,
        fn (PurgeEdgeCache $job) => $job->tags === ['brands']
            && $job->extraPaths === ["/brands/{$brand->slug}"]
            && $job->project === $this->project->username,
    );
});

test('a rundown-only change queues no brand detail purge', function () {
    config(['edge-sites.token' => 'token-abc']);

    BrandEvent::factory()->create([
        'brand_id' => Brand::factory(),
        'event_id' => $this->event->id,
        'status' => 'active',
    ]);

    Queue::fake();

    $this->actingAs(visibilityMaster())
        ->putJson($this->endpoint, ['rundown_public_visible' => false])
        ->assertOk();

    Queue::assertNotPushed(
        PurgeEdgeCache::class,
        fn (PurgeEdgeCache $job) => $job->extraPaths !== [],
    );
});

test('stays off the network when no edge purge token is configured', function () {
    config(['edge-sites.token' => null]);

    BrandEvent::factory()->create([
        'brand_id' => Brand::factory(),
        'event_id' => $this->event->id,
        'status' => 'active',
    ]);

    Queue::fake();

    $this->actingAs(visibilityMaster())
        ->putJson($this->endpoint, ['brands_public_visible' => false])
        ->assertOk();

    Queue::assertNotPushed(PurgeEdgeCache::class);
});
