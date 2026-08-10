<?php

use App\Jobs\PurgeEdgeAfterBuild;
use App\Jobs\TriggerWorkerBuild;
use App\Models\Event;
use App\Models\Faq;
use App\Models\Project;
use App\Models\User;
use App\Support\WorkersBuilds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

// ---------------------------------------------------------------------------
// Trigger selection — a Worker has two, and the wrong one breaks the deploy
// ---------------------------------------------------------------------------

/** Cloudflare's default pair: preview first in the list, production second. */
function fakeTwoTriggers(): void
{
    Http::fake([
        CF.'/accounts/acct123/workers/scripts*' => Http::response([
            'success' => true,
            'result' => [['id' => 'megabuild', 'tag' => 'tag-mb']],
        ]),
        CF.'/accounts/acct123/builds/workers/*/triggers*' => Http::response([
            'success' => true,
            'result' => [
                [
                    'trigger_uuid' => 'trig-preview',
                    'trigger_name' => 'Deploy non-production branches',
                    'branch_includes' => ['*'],
                    'branch_excludes' => ['main'],
                    'deploy_command' => 'npx wrangler versions upload',
                ],
                [
                    'trigger_uuid' => 'trig-production',
                    'trigger_name' => 'Deploy default branch',
                    'branch_includes' => ['main'],
                    'branch_excludes' => [],
                    'deploy_command' => 'npx wrangler --cwd apps/megabuild/.output deploy',
                ],
            ],
        ]),
        CF.'/accounts/acct123/builds/triggers/*/builds' => Http::response([
            'success' => true,
            'result' => ['build_uuid' => 'b9', 'status' => 'queued'],
        ]),
    ]);
}

it('triggers the production trigger, not the preview one listed first', function () {
    fakeTwoTriggers();

    expect(WorkersBuilds::trigger('megabuild'))->toBeTrue();

    Http::assertSent(fn ($r) => str_contains($r->url(), '/builds/triggers/trig-production/builds'));
    Http::assertNotSent(fn ($r) => str_contains($r->url(), '/builds/triggers/trig-preview/builds'));
});

it('falls back to a wildcard trigger when no exact branch match exists', function () {
    config(['edge-sites.builds_branch' => 'staging']);
    fakeTwoTriggers();

    // 'staging' is not excluded by the preview trigger and matches its '*'.
    expect(WorkersBuilds::trigger('megabuild'))->toBeTrue();
    Http::assertSent(fn ($r) => str_contains($r->url(), '/builds/triggers/trig-preview/builds'));
});

it('refuses to trigger when every trigger excludes the branch', function () {
    config(['edge-sites.builds_branch' => 'main']);
    Http::fake([
        CF.'/accounts/acct123/workers/scripts*' => Http::response([
            'success' => true,
            'result' => [['id' => 'megabuild', 'tag' => 'tag-mb']],
        ]),
        CF.'/accounts/acct123/builds/workers/*/triggers*' => Http::response([
            'success' => true,
            'result' => [[
                'trigger_uuid' => 'trig-preview',
                'branch_includes' => ['*'],
                'branch_excludes' => ['main'],
            ]],
        ]),
    ]);

    expect(WorkersBuilds::trigger('megabuild'))->toBeFalse();
    Http::assertNotSent(fn ($r) => str_contains($r->url(), '/builds/triggers/'));
});

// ---------------------------------------------------------------------------
// Staleness: does this site need rebuilding?
// ---------------------------------------------------------------------------

/** Set a project's updated_at without Eloquent refreshing it back to now(). */
function touchProject(string $username, string $at): void
{
    DB::table('projects')
        ->where('username', $username)
        ->update(['updated_at' => $at]);
}

it('flags a site whose content changed after its last successful build', function () {
    Project::factory()->create(['username' => 'megabuild', 'name' => 'Megabuild']);
    Project::factory()->create(['username' => 'icc', 'name' => 'ICC']);
    // Straight to the table: saveQuietly() still refreshes `updated_at`.
    // Local time (app.timezone = Asia/Jakarta); Cloudflare reports UTC. Values
    // are picked far enough apart that the offset cannot flip the comparison.
    touchProject('megabuild', '2026-08-07 12:00:00');   // 05:00Z, AFTER the build
    touchProject('icc', '2026-08-07 08:00:00');         // 01:00Z, BEFORE the build

    fakeCloudflare([
        // megabuild shipped BEFORE the content edit -> stale
        'tag-mb' => [
            'build_uuid' => 'b1', 'status' => 'stopped', 'build_outcome' => 'success',
            'created_on' => '2026-08-07T03:00:00Z', 'stopped_on' => '2026-08-07T03:05:00Z',   // 10:05 Jakarta
        ],
        // icc shipped AFTER its content edit -> current
        'tag-icc' => [
            'build_uuid' => 'b2', 'status' => 'stopped', 'build_outcome' => 'success',
            'created_on' => '2026-08-07T04:00:00Z', 'stopped_on' => '2026-08-07T04:05:00Z',   // 11:05 Jakarta
        ],
    ]);

    $this->getJson('/api/websites')
        ->assertSuccessful()
        ->assertJsonPath('data.0.needs_rebuild', true)
        ->assertJsonPath('data.1.needs_rebuild', false);
});

it('treats a failed build as never having shipped', function () {
    Project::factory()->create(['username' => 'megabuild']);
    touchProject('megabuild', '2026-08-07 01:00:00');   // 2026-08-06 18:00Z

    fakeCloudflare([
        'tag-mb' => [
            'build_uuid' => 'b1', 'status' => 'stopped', 'build_outcome' => 'fail',
            'stopped_on' => '2026-08-07T09:00:00Z',
        ],
    ]);

    // The build finished AFTER the edit, but it failed, so nothing shipped.
    $this->getJson('/api/websites')
        ->assertSuccessful()
        ->assertJsonPath('data.0.needs_rebuild', true);
});

// ---------------------------------------------------------------------------
// Detail, logs, cancel
// ---------------------------------------------------------------------------

it('returns build history for one site', function () {
    Http::fake([
        CF.'/accounts/acct123/workers/scripts*' => Http::response([
            'success' => true, 'result' => [['id' => 'megabuild', 'tag' => 'tag-mb']],
        ]),
        CF.'/accounts/acct123/builds/workers/*/builds*' => Http::response([
            'success' => true,
            'result' => [
                ['build_uuid' => 'b1', 'status' => 'stopped', 'build_outcome' => 'success'],
                ['build_uuid' => 'b0', 'status' => 'stopped', 'build_outcome' => 'fail'],
            ],
        ]),
    ]);

    $this->getJson('/api/websites/megabuild')
        ->assertSuccessful()
        ->assertJsonPath('data.worker', 'megabuild')
        ->assertJsonCount(2, 'data.history')
        ->assertJsonPath('data.history.0.outcome', 'success');
});

it('404s for an unknown website', function () {
    $this->getJson('/api/websites/not-a-site')->assertNotFound();
});

it('returns build log lines with the moment each was written', function () {
    Http::fake([
        CF.'/accounts/acct123/builds/builds/*/logs*' => Http::response([
            'success' => true,
            'result' => [
                'lines' => [
                    [1786000000, 'installing'],       // seconds
                    [1786000001500, 'done'],          // milliseconds
                    [1, 'legacy'],                    // not an epoch at all
                ],
                'truncated' => false,
            ],
        ]),
    ]);

    $response = $this->getJson('/api/websites/megabuild/builds/b1/logs')
        ->assertSuccessful()
        ->assertJsonPath('data.lines.0.text', 'installing')
        ->assertJsonPath('data.lines.1.text', 'done')
        // A timestamp that cannot be an epoch still leaves the line readable.
        ->assertJsonPath('data.lines.2.at', null)
        ->assertJsonPath('data.lines.2.text', 'legacy')
        ->assertJsonPath('data.truncated', false);

    $lines = $response->json('data.lines');

    expect($lines[0]['at'])->not->toBeNull()
        ->and($lines[1]['at'])->not->toBeNull()
        // Both timestamps land within two seconds of each other; the second one
        // was only bigger because Cloudflare sent it in milliseconds.
        ->and(abs(strtotime($lines[1]['at']) - strtotime($lines[0]['at'])))->toBeLessThan(5);
});

it('cancels a build', function () {
    Http::fake([CF.'/accounts/acct123/builds/builds/*/cancel' => Http::response(['success' => true])]);

    $this->postJson('/api/websites/megabuild/builds/b1/cancel')
        ->assertSuccessful()
        ->assertJsonPath('message', 'Build cancelled.');
});

it('surfaces a Cloudflare refusal to cancel', function () {
    Http::fake([CF.'/accounts/acct123/builds/builds/*/cancel' => Http::response(['success' => false], 400)]);

    $this->postJson('/api/websites/megabuild/builds/b1/cancel')->assertStatus(502);
});

it('requires the rebuild permission to cancel', function () {
    $other = User::factory()->create(['email_verified_at' => now()]);
    Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web'])
        ->syncPermissions([Permission::findByName('websites.view')]);
    $other->assignRole('viewer');
    $this->actingAs($other);

    $this->getJson('/api/websites/megabuild')->assertSuccessful();
    $this->postJson('/api/websites/megabuild/builds/b1/cancel')->assertForbidden();
});

it('compares a local content timestamp against Cloudflare UTC correctly', function () {
    Project::factory()->create(['username' => 'megabuild']);
    // 2026-08-07 06:00 Jakarta == 2026-08-06 23:00Z. A naive string comparison
    // would call this newer than the build below; it is not.
    touchProject('megabuild', '2026-08-07 06:00:00');

    fakeCloudflare([
        'tag-mb' => [
            'build_uuid' => 'b1', 'status' => 'stopped', 'build_outcome' => 'success',
            'stopped_on' => '2026-08-07T00:00:00Z',
        ],
    ]);

    $this->getJson('/api/websites')
        ->assertSuccessful()
        ->assertJsonPath('data.0.needs_rebuild', false);
});

// ---------------------------------------------------------------------------
// Edge purge after a build — a rebuild nobody can see is not a rebuild
// ---------------------------------------------------------------------------

it('schedules an edge purge after a trigger succeeds', function () {
    Queue::fake();
    fakeCloudflare();

    (new TriggerWorkerBuild('megabuild'))->handle();

    Queue::assertPushed(PurgeEdgeAfterBuild::class,
        fn ($job) => $job->worker === 'megabuild');
});

it('does not schedule a purge when the trigger fails', function () {
    Queue::fake();
    Http::fake(fn () => Http::response(['success' => false], 500));

    (new TriggerWorkerBuild('megabuild'))->handle();

    Queue::assertNotPushed(PurgeEdgeAfterBuild::class);
});

it('re-checks instead of purging while the build is still running', function () {
    Queue::fake();
    fakeCloudflare(['tag-mb' => ['build_uuid' => 'b1', 'status' => 'running']]);

    (new PurgeEdgeAfterBuild('megabuild'))->handle();

    Queue::assertPushed(PurgeEdgeAfterBuild::class, fn ($job) => $job->check === 1);
});

it('does not purge after a failed build', function () {
    Queue::fake();
    fakeCloudflare([
        'tag-mb' => ['build_uuid' => 'b1', 'status' => 'stopped', 'build_outcome' => 'fail'],
    ]);

    (new PurgeEdgeAfterBuild('megabuild'))->handle();

    // Nothing rescheduled, and no purge call went out.
    Queue::assertNotPushed(PurgeEdgeAfterBuild::class);
    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'purge_cache'));
});

it('gives up after the maximum number of checks', function () {
    Queue::fake();
    fakeCloudflare(['tag-mb' => ['build_uuid' => 'b1', 'status' => 'running']]);

    (new PurgeEdgeAfterBuild('megabuild', 40))->handle();

    Queue::assertNotPushed(PurgeEdgeAfterBuild::class);
});

/*
|--------------------------------------------------------------------------
| Sites that render no PM One content
|--------------------------------------------------------------------------
|
| The dashboard itself, Levenium and Monara are built and monitored here but
| own no project. `project` is deliberately null so nothing can ever match them
| in a content-driven purge — see the note in config/edge-sites.php.
|
*/

function configureProjectlessSite(): void
{
    config(['edge-sites.sites' => array_merge(config('edge-sites.sites'), [
        [
            'app' => 'pmone',
            'project' => null,
            'name' => 'PM One',
            'data_source' => null,
            'url' => 'https://pmone.id',
            'locales' => ['en'],
        ],
    ])]);

    Http::fake([
        CF.'/accounts/acct123/workers/scripts*' => Http::response([
            'success' => true,
            'result' => [
                ['id' => 'megabuild', 'tag' => 'tag-mb'],
                ['id' => 'icc', 'tag' => 'tag-icc'],
                ['id' => 'pmone', 'tag' => 'tag-pmone'],
            ],
        ]),
        CF.'/accounts/acct123/builds/workers/*/triggers*' => Http::response([
            'success' => true,
            'result' => [['trigger_uuid' => 'trig-1']],
        ]),
        CF.'/accounts/acct123/builds/builds/latest*' => Http::response([
            'success' => true,
            'result' => ['builds' => []],
        ]),
        CF.'/accounts/acct123/builds/triggers/*/builds' => Http::response([
            'success' => true,
            'result' => ['build_uuid' => 'build-1', 'status' => 'queued'],
        ]),
    ]);
}

it('lists a site that owns no project, using its configured name', function () {
    configureProjectlessSite();

    $this->getJson('/api/websites')
        ->assertSuccessful()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.2.worker', 'pmone')
        ->assertJsonPath('data.2.project', null)
        ->assertJsonPath('data.2.project_name', 'PM One');
});

it('never marks a projectless site as outdated', function () {
    configureProjectlessSite();

    // A project edited right now must not make the dashboard look stale: there
    // is no PM One content behind it that a rebuild would pick up.
    Project::factory()->create(['username' => 'megabuild', 'name' => 'Megabuild Indonesia']);
    DB::table('projects')->where('username', 'megabuild')->update(['updated_at' => now()]);

    $this->getJson('/api/websites')
        ->assertSuccessful()
        ->assertJsonPath('data.2.needs_rebuild', false)
        ->assertJsonPath('data.2.content_changed_at', null);
});

it('rebuilds a projectless site like any other', function () {
    configureProjectlessSite();
    Queue::fake();

    $this->postJson('/api/websites/rebuild', ['workers' => ['pmone']])
        ->assertSuccessful();

    Queue::assertPushed(TriggerWorkerBuild::class, fn ($job) => $job->worker === 'pmone');
});

// ---------------------------------------------------------------------------
// Build duration
// ---------------------------------------------------------------------------

it('reports how long a build ran and how long it waited', function () {
    fakeCloudflare([
        'tag-mb' => [
            'build_uuid' => 'b1', 'status' => 'stopped', 'build_outcome' => 'success',
            'created_on' => '2026-08-07T02:00:00Z',
            'running_on' => '2026-08-07T02:00:30Z',
            'stopped_on' => '2026-08-07T02:06:00Z',
        ],
    ]);

    $this->getJson('/api/websites')
        ->assertSuccessful()
        ->assertJsonPath('data.0.build.duration_seconds', 330)
        ->assertJsonPath('data.0.build.queued_seconds', 30);
});

it('leaves the duration open while a build is still running', function () {
    fakeCloudflare([
        'tag-mb' => [
            'build_uuid' => 'b2', 'status' => 'running',
            'created_on' => '2026-08-07T02:00:00Z',
            'running_on' => '2026-08-07T02:00:30Z',
        ],
    ]);

    $this->getJson('/api/websites')
        ->assertSuccessful()
        ->assertJsonPath('data.0.build.duration_seconds', null)
        ->assertJsonPath('data.0.build.queued_seconds', 30);
});

// ---------------------------------------------------------------------------
// What a site is waiting to publish
// ---------------------------------------------------------------------------

/** A project with one event and one FAQ, every timestamp under the test's control. */
function contentFor(string $username, string $projectAt, ?string $faqAt = null): Event
{
    $project = Project::factory()->create(['username' => $username]);
    $event = Event::factory()->create(['project_id' => $project->id]);

    DB::table('projects')->where('id', $project->id)->update(['updated_at' => $projectAt]);
    DB::table('events')->where('id', $event->id)->update(['updated_at' => '2020-01-01 00:00:00']);

    if ($faqAt) {
        $faq = Faq::factory()->create(['event_id' => $event->id]);
        DB::table('faqs')->where('id', $faq->id)->update(['updated_at' => $faqAt]);
    }

    return $event;
}

it('lists only the sources that have not shipped yet', function () {
    // The FAQ was edited after the build; the project row long before it.
    contentFor('megabuild', '2026-08-06 09:00:00', '2026-08-07 12:00:00');

    fakeCloudflare([
        'tag-mb' => [
            'build_uuid' => 'b1', 'status' => 'stopped', 'build_outcome' => 'success',
            'stopped_on' => '2026-08-07T02:00:00Z',   // 09:00 Jakarta
        ],
    ]);

    $this->getJson('/api/websites')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.0.content_changes')
        ->assertJsonPath('data.0.content_changes.0.source', 'faqs')
        ->assertJsonPath('data.0.content_changes.0.label', 'FAQ');
});

it('keeps needs_rebuild and content_changes in step', function () {
    contentFor('megabuild', '2026-08-07 12:00:00');
    contentFor('icc', '2026-08-01 12:00:00');

    fakeCloudflare([
        'tag-mb' => [
            'build_uuid' => 'b1', 'status' => 'stopped', 'build_outcome' => 'success',
            'stopped_on' => '2026-08-07T02:00:00Z',
        ],
        'tag-icc' => [
            'build_uuid' => 'b2', 'status' => 'stopped', 'build_outcome' => 'success',
            'stopped_on' => '2026-08-07T02:00:00Z',
        ],
    ]);

    $rows = $this->getJson('/api/websites')->assertSuccessful()->json('data');

    foreach ($rows as $row) {
        expect($row['needs_rebuild'])->toBe(count($row['content_changes']) > 0);
    }

    expect($rows[0]['needs_rebuild'])->toBeTrue()
        ->and($rows[1]['needs_rebuild'])->toBeFalse();
});

it('lists every source when no build has ever shipped', function () {
    contentFor('megabuild', '2026-08-06 09:00:00', '2026-08-07 12:00:00');

    fakeCloudflare();

    $this->getJson('/api/websites')
        ->assertSuccessful()
        ->assertJsonPath('data.0.needs_rebuild', true)
        // project, event and faq: nothing has been published, so nothing is safe.
        ->assertJsonCount(3, 'data.0.content_changes');
});

it('reports no pending changes for a projectless site', function () {
    configureProjectlessSite();
    contentFor('megabuild', '2026-08-07 12:00:00');

    $this->getJson('/api/websites')
        ->assertSuccessful()
        ->assertJsonPath('data.2.content_changes', []);
});

// ---------------------------------------------------------------------------
// The changes endpoint behind the hover card
// ---------------------------------------------------------------------------

it('returns the individual edits behind a site', function () {
    $event = contentFor('megabuild', '2026-08-06 09:00:00');

    $faq = Faq::factory()->create([
        'event_id' => $event->id,
        'question' => ['en' => 'Where is the venue?'],
    ]);
    DB::table('faqs')->where('id', $faq->id)->update(['updated_at' => '2026-08-07 12:00:00']);

    $this->getJson('/api/websites/megabuild/changes')
        ->assertSuccessful()
        ->assertJsonPath('data.project', 'megabuild')
        ->assertJsonPath('data.items.0.source', 'faqs')
        ->assertJsonPath('data.items.0.title', 'Where is the venue?');
});

it('drops edits older than the given cut-off', function () {
    $event = contentFor('megabuild', '2026-08-01 09:00:00');

    $faq = Faq::factory()->create(['event_id' => $event->id]);
    DB::table('faqs')->where('id', $faq->id)->update(['updated_at' => '2026-08-07 12:00:00']);

    $items = $this->getJson('/api/websites/megabuild/changes?since=2026-08-07T03:00:00Z')
        ->assertSuccessful()
        ->json('data.items');

    expect(array_column($items, 'source'))->toBe(['faqs']);
});

it('rejects a cut-off that is not a date', function () {
    $this->getJson('/api/websites/megabuild/changes?since=whenever')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('since');
});

it('reads the changes of the project a co-located site renders', function () {
    config(['edge-sites.sites' => array_merge(config('edge-sites.sites'), [
        ['app' => 'cokelatexpo', 'project' => 'cei', 'data_source' => 'cbe', 'url' => 'https://cokelatexpo.id', 'locales' => ['en']],
    ])]);

    // Its own project is quiet; the project it READS from is not.
    contentFor('cei', '2026-08-01 09:00:00');
    $cbe = contentFor('cbe', '2026-08-01 09:00:00');

    $faq = Faq::factory()->create(['event_id' => $cbe->id, 'question' => ['en' => 'A cbe question']]);
    DB::table('faqs')->where('id', $faq->id)->update(['updated_at' => '2026-08-07 12:00:00']);

    $this->getJson('/api/websites/cokelatexpo/changes')
        ->assertSuccessful()
        ->assertJsonPath('data.project', 'cbe')
        ->assertJsonPath('data.items.0.title', 'A cbe question');
});

it('returns an empty change list for a projectless site', function () {
    configureProjectlessSite();

    $this->getJson('/api/websites/pmone/changes')
        ->assertSuccessful()
        ->assertJsonPath('data.project', null)
        ->assertJsonPath('data.items', []);
});

it('404s the changes of an unknown website', function () {
    $this->getJson('/api/websites/not-a-site/changes')->assertNotFound();
});

// ---------------------------------------------------------------------------
// Detail summary
// ---------------------------------------------------------------------------

/** Cloudflare with a build history for megabuild. */
function fakeHistory(array $builds): void
{
    Http::fake([
        CF.'/accounts/acct123/workers/scripts*' => Http::response([
            'success' => true, 'result' => [['id' => 'megabuild', 'tag' => 'tag-mb']],
        ]),
        CF.'/accounts/acct123/builds/workers/*/builds*' => Http::response([
            'success' => true, 'result' => $builds,
        ]),
        CF.'/accounts/acct123/builds/account/limits' => Http::response([
            'success' => true,
            'result' => ['has_reached_build_minutes_limit' => false, 'build_minutes_refresh_on' => null],
        ]),
    ]);
}

it('summarises the site on the detail endpoint', function () {
    Project::factory()->create(['username' => 'megabuild', 'name' => 'Megabuild Indonesia']);
    // A factory stamps updated_at with now(), which is newer than any fixture
    // build below and would make the site look stale on a technicality.
    touchProject('megabuild', '2026-08-01 09:00:00');

    fakeHistory([
        [
            'build_uuid' => 'b1', 'status' => 'stopped', 'build_outcome' => 'success',
            'created_on' => '2026-08-07T02:00:00Z',
            'running_on' => '2026-08-07T02:00:30Z',
            'stopped_on' => '2026-08-07T02:06:00Z',
        ],
    ]);

    $this->getJson('/api/websites/megabuild')
        ->assertSuccessful()
        ->assertJsonPath('data.project_name', 'Megabuild Indonesia')
        ->assertJsonPath('data.content_project', 'megabuild')
        ->assertJsonPath('data.build.uuid', 'b1')
        ->assertJsonPath('data.build.duration_seconds', 330)
        ->assertJsonPath('data.needs_rebuild', false)
        ->assertJsonPath('data.content_changes', [])
        ->assertJsonPath('meta.configured', true);
});

it('averages only the builds that succeeded', function () {
    fakeHistory([
        // 300s success, 20s failure, 400s success -> 350s average, 67% success.
        ['build_uuid' => 'b3', 'status' => 'stopped', 'build_outcome' => 'success',
            'running_on' => '2026-08-07T03:00:00Z', 'stopped_on' => '2026-08-07T03:05:00Z'],
        ['build_uuid' => 'b2', 'status' => 'stopped', 'build_outcome' => 'fail',
            'running_on' => '2026-08-07T02:00:00Z', 'stopped_on' => '2026-08-07T02:00:20Z'],
        ['build_uuid' => 'b1', 'status' => 'stopped', 'build_outcome' => 'success',
            'running_on' => '2026-08-07T01:00:00Z', 'stopped_on' => '2026-08-07T01:06:40Z'],
    ]);

    $this->getJson('/api/websites/megabuild')
        ->assertSuccessful()
        ->assertJsonPath('data.stats.builds', 3)
        ->assertJsonPath('data.stats.succeeded', 2)
        ->assertJsonPath('data.stats.success_rate', 67)
        ->assertJsonPath('data.stats.avg_duration_seconds', 350)
        ->assertJsonPath('data.stats.last_success_at', '2026-08-07T03:05:00Z')
        ->assertJsonPath('data.stats.outcomes', ['success', 'fail', 'success']);
});

it('reports empty stats when Cloudflare has nothing to say', function () {
    Http::fake(fn () => Http::response(['success' => false], 500));

    $this->getJson('/api/websites/megabuild')
        ->assertSuccessful()
        ->assertJsonPath('data.build', null)
        ->assertJsonPath('data.stats.builds', 0)
        ->assertJsonPath('data.stats.success_rate', null)
        ->assertJsonPath('data.stats.avg_duration_seconds', null);
});
