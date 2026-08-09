<?php

use App\Console\Commands\PurgeEdgeCacheAfterBuild;
use App\Support\WorkersBuilds;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The scheduled sweep that drops a site's edge cache once its Worker has been
 * redeployed. Deploying a Worker does not purge the zone, and cached HTML
 * embeds hashed /_nuxt chunk URLs the deploy has just replaced — with a 30-day
 * article TTL, missing a build means serving broken pages for a month.
 */
const CF_API = 'https://api.cloudflare.com/client/v4';

beforeEach(function () {
    config([
        'edge-sites.token' => 'purge-token',
        'edge-sites.builds_token' => 'builds-token',
        'services.cloudflare.account_id' => 'acct123',
        'edge-sites.sites' => [
            ['app' => 'megabuild', 'project' => 'megabuild', 'data_source' => null, 'url' => 'https://megabuild.co.id', 'locales' => ['en']],
            ['app' => 'icc', 'project' => 'icc', 'data_source' => null, 'url' => 'https://indonesiacomiccon.com', 'locales' => ['en']],
        ],
    ]);

    Cache::flush();
    WorkersBuilds::forgetLookups();
});

/**
 * @param  array<string, array{uuid: string, status?: string, outcome?: string}>  $builds
 * @return array<string, mixed>
 */
function buildsBody(array $builds): array
{
    $result = [];
    foreach ($builds as $worker => $build) {
        $result['tag-'.$worker] = [
            'build_uuid' => $build['uuid'],
            'status' => $build['status'] ?? 'stopped',
            'build_outcome' => $build['outcome'] ?? 'success',
        ];
    }

    return ['success' => true, 'result' => ['builds' => $result]];
}

/**
 * Fake Cloudflare once per test.
 *
 * ONE call to Http::fake(), and no Http::fakeSequence(). Both bite here:
 * fake() APPENDS its stubs rather than replacing them, and fakeSequence() with
 * no URL pattern registers a catch-all — either one silently swallows every
 * request and turns an assertion into a pass for the wrong reason.
 *
 * The builds endpoint is a closure over a queue instead, so a test can hand the
 * command a different build on its second sweep. The last entry repeats once the
 * queue runs down, so a test with one sweep can be called any number of times.
 *
 * @param  array<int, array<string, array{uuid: string, status?: string, outcome?: string}>>  $sweeps
 */
function fakeCloudflareBuilds(array $sweeps): void
{
    $queue = $sweeps;

    Http::fake([
        CF_API.'/accounts/acct123/builds/builds/latest*' => function () use (&$queue) {
            $builds = count($queue) > 1 ? array_shift($queue) : $queue[0];

            return Http::response(buildsBody($builds));
        },
        CF_API.'/accounts/acct123/workers/scripts*' => Http::response(['success' => true, 'result' => [
            ['id' => 'megabuild', 'tag' => 'tag-megabuild'],
            ['id' => 'icc', 'tag' => 'tag-icc'],
        ]]),
        CF_API.'/zones*' => Http::response(['success' => true, 'result' => [
            ['name' => 'megabuild.co.id', 'id' => 'zone-mb'],
            ['name' => 'indonesiacomiccon.com', 'id' => 'zone-icc'],
        ], 'result_info' => ['total_pages' => 1]]),
        CF_API.'/zones/*/purge_cache' => Http::response(['success' => true]),
    ]);
}

/** @return string[] zone ids that received a purge_everything call */
function purgedZones(): array
{
    $zones = [];

    foreach (Http::recorded() as [$request]) {
        if (str_contains($request->url(), '/purge_cache') && ($request->data()['purge_everything'] ?? false)) {
            preg_match('#/zones/([^/]+)/purge_cache#', $request->url(), $m);
            $zones[] = $m[1] ?? '?';
        }
    }

    return $zones;
}

it('purges a site whose worker has a new successful build', function () {
    fakeCloudflareBuilds([['megabuild' => ['uuid' => 'build-1'], 'icc' => ['uuid' => 'build-2']]]);

    $this->artisan('edge-cache:purge-after-build')->assertSuccessful();

    expect(purgedZones())->toContain('zone-mb')->toContain('zone-icc');
});

it('remembers the build so the next sweep purges nothing', function () {
    $same = ['megabuild' => ['uuid' => 'build-1'], 'icc' => ['uuid' => 'build-2']];
    fakeCloudflareBuilds([$same, $same]);

    $this->artisan('edge-cache:purge-after-build')->assertSuccessful();
    $afterFirst = count(purgedZones());

    $this->artisan('edge-cache:purge-after-build')->assertSuccessful();

    expect($afterFirst)->toBe(2)
        ->and(purgedZones())->toHaveCount(2);
});

it('purges again once the build id changes', function () {
    fakeCloudflareBuilds([
        ['megabuild' => ['uuid' => 'build-1']],
        ['megabuild' => ['uuid' => 'build-2']],
    ]);

    $this->artisan('edge-cache:purge-after-build')->assertSuccessful();
    $this->artisan('edge-cache:purge-after-build')->assertSuccessful();

    expect(purgedZones())->toBe(['zone-mb', 'zone-mb'])
        ->and(Cache::get(PurgeEdgeCacheAfterBuild::SEEN_KEY.'megabuild'))->toBe('build-2');
});

it('ignores a build that is still running', function () {
    fakeCloudflareBuilds([['megabuild' => ['uuid' => 'build-1', 'status' => 'running', 'outcome' => 'success']]]);

    $this->artisan('edge-cache:purge-after-build')->assertSuccessful();

    expect(purgedZones())->toBeEmpty()
        ->and(Cache::get(PurgeEdgeCacheAfterBuild::SEEN_KEY.'megabuild'))->toBeNull();
});

it('ignores a build that stopped without succeeding', function () {
    // "stopped" is terminal but neutral — a failed or cancelled build replaced
    // no assets, so the cached page is still the correct one.
    fakeCloudflareBuilds([['megabuild' => ['uuid' => 'build-1', 'outcome' => 'fail']]]);

    $this->artisan('edge-cache:purge-after-build')->assertSuccessful();

    expect(purgedZones())->toBeEmpty();
});

it('purges nothing on a dry run', function () {
    fakeCloudflareBuilds([['megabuild' => ['uuid' => 'build-1']]]);

    $this->artisan('edge-cache:purge-after-build', ['--dry-run' => true])->assertSuccessful();

    expect(purgedZones())->toBeEmpty()
        ->and(Cache::get(PurgeEdgeCacheAfterBuild::SEEN_KEY.'megabuild'))->toBeNull();
});
