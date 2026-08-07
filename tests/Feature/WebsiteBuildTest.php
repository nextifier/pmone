<?php

use App\Jobs\TriggerWorkerBuild;
use App\Models\Project;
use App\Models\User;
use App\Support\WorkersBuilds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

const CF = 'https://api.cloudflare.com/client/v4';

beforeEach(function () {
    config([
        'edge-sites.builds_token' => 'test-token',
        'edge-sites.builds_branch' => 'main',
        'services.cloudflare.account_id' => 'acct123',
        'edge-sites.sites' => [
            ['app' => 'megabuild', 'project' => 'megabuild', 'data_source' => null, 'url' => 'https://megabuild.co.id', 'locales' => ['en']],
            ['app' => 'icc', 'project' => 'icc', 'data_source' => null, 'url' => 'https://indonesiacomiccon.com', 'locales' => ['en']],
        ],
    ]);

    Cache::flush();
    WorkersBuilds::forgetLookups();

    foreach (['websites.view', 'websites.rebuild'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web'])->syncPermissions(Permission::all());

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);
});

/** Cloudflare responses for the two lookups plus the batched status call. */
function fakeCloudflare(array $builds = []): void
{
    Http::fake([
        CF.'/accounts/acct123/workers/scripts*' => Http::response([
            'success' => true,
            'result' => [
                ['id' => 'megabuild', 'tag' => 'tag-mb'],
                ['id' => 'icc', 'tag' => 'tag-icc'],
            ],
        ]),
        CF.'/accounts/acct123/builds/workers/*/triggers*' => Http::response([
            'success' => true,
            'result' => [['trigger_uuid' => 'trig-1']],
        ]),
        CF.'/accounts/acct123/builds/builds/latest*' => Http::response([
            'success' => true,
            'result' => ['builds' => $builds],
        ]),
        CF.'/accounts/acct123/builds/triggers/*/builds' => Http::response([
            'success' => true,
            'result' => ['build_uuid' => 'build-1', 'status' => 'queued'],
        ]),
    ]);
}

it('lists every configured site with its latest build', function () {
    Project::factory()->create(['username' => 'megabuild', 'name' => 'Megabuild Indonesia']);

    fakeCloudflare([
        'tag-mb' => [
            'build_uuid' => 'b1',
            'status' => 'stopped',
            'build_outcome' => 'success',
            'created_on' => '2026-08-07T02:00:00Z',
            'stopped_on' => '2026-08-07T02:06:00Z',
            'build_trigger_metadata' => [
                'branch' => 'main',
                'commit_hash' => 'abc1234',
                'commit_message' => 'updates',
                'author' => 'Nextifier',
                'build_trigger_source' => 'push',
            ],
        ],
    ]);

    $this->getJson('/api/websites')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.configured', true)
        ->assertJsonPath('data.0.worker', 'megabuild')
        ->assertJsonPath('data.0.project_name', 'Megabuild Indonesia')
        ->assertJsonPath('data.0.build.status', 'stopped')
        ->assertJsonPath('data.0.build.outcome', 'success')
        ->assertJsonPath('data.0.build.commit_message', 'updates')
        // No build reported for icc: the row still renders, just without one.
        ->assertJsonPath('data.1.worker', 'icc')
        ->assertJsonPath('data.1.build', null);
});

it('reports an in-flight build without an outcome', function () {
    fakeCloudflare([
        'tag-mb' => ['build_uuid' => 'b2', 'status' => 'running', 'created_on' => '2026-08-07T02:00:00Z'],
    ]);

    $this->getJson('/api/websites')
        ->assertSuccessful()
        ->assertJsonPath('data.0.build.status', 'running')
        ->assertJsonPath('data.0.build.outcome', null);
});

it('still lists the sites when Cloudflare is unreachable', function () {
    Http::fake(fn () => Http::response(['success' => false, 'errors' => [['message' => 'boom']]], 500));

    $this->getJson('/api/websites')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.build', null);
});

it('reports itself unconfigured when no builds token is set', function () {
    config(['edge-sites.builds_token' => null]);

    $this->getJson('/api/websites')
        ->assertSuccessful()
        ->assertJsonPath('meta.configured', false);
});

it('queues one job per selected website', function () {
    Queue::fake();
    fakeCloudflare();

    $this->postJson('/api/websites/rebuild', ['workers' => ['megabuild', 'icc']])
        ->assertSuccessful()
        ->assertJsonPath('message', '2 rebuilds queued.');

    Queue::assertPushed(TriggerWorkerBuild::class, 2);
    Queue::assertPushed(TriggerWorkerBuild::class, fn ($job) => $job->worker === 'megabuild');
});

it('de-duplicates a repeated selection', function () {
    Queue::fake();

    $this->postJson('/api/websites/rebuild', ['workers' => ['icc', 'icc']])
        ->assertSuccessful()
        ->assertJsonPath('message', 'Rebuild queued.');

    Queue::assertPushed(TriggerWorkerBuild::class, 1);
});

it('rejects an unknown website', function () {
    Queue::fake();

    $this->postJson('/api/websites/rebuild', ['workers' => ['not-a-site']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('workers.0');

    Queue::assertNothingPushed();
});

it('rejects an empty selection', function () {
    $this->postJson('/api/websites/rebuild', ['workers' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('workers');
});

it('refuses to rebuild when Cloudflare is not configured', function () {
    Queue::fake();
    config(['edge-sites.builds_token' => null]);

    $this->postJson('/api/websites/rebuild', ['workers' => ['icc']])
        ->assertStatus(503);

    Queue::assertNothingPushed();
});

it('sends the configured branch to the trigger endpoint', function () {
    fakeCloudflare();

    expect(WorkersBuilds::trigger('megabuild'))->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/builds/triggers/trig-1/builds')
        && $request['branch'] === 'main');
});

it('hides both endpoints from a user without the permissions', function () {
    $other = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($other);

    $this->getJson('/api/websites')->assertForbidden();
    $this->postJson('/api/websites/rebuild', ['workers' => ['icc']])->assertForbidden();
});
