<?php

use App\Models\Project;
use Database\Seeders\WebsiteConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds nav, analytics and identity into site_config from the baked seed data', function () {
    $project = Project::factory()->create(['username' => 'megabuild']);

    $this->seed(WebsiteConfigSeeder::class);

    $siteConfig = data_get($project->refresh()->settings, 'website_settings.site_config');

    expect($siteConfig['analytics'])->toBe([
        'ga4' => 'G-2PJCW7S32V',
        'tiktok_pixel' => null,
    ]);

    expect($siteConfig['identity'])->toBe([
        'company_name' => 'PT Panorama Media',
        'company_address' => 'Panorama Media Building, Jl. Tanjung Selor No.17A, RT.11/RW.6, Cideng, Kecamatan Gambir, Kota Jakarta Pusat, Daerah Khusus Ibukota Jakarta 10150',
    ]);

    expect($siteConfig['nav']['header'])->toBeArray()->not->toBeEmpty();
    expect($siteConfig['nav']['header'][0])->toBe(['label' => 'Home', 'path' => '/']);
    expect($siteConfig['nav']['dialog'])->toBeArray()->not->toBeEmpty();
    expect($siteConfig['nav']['footer'])->toBeArray()->not->toBeEmpty();
});

it('leaves ga4 null for the shared cbe project so each app keeps its own baked GA4', function () {
    $project = Project::factory()->create(['username' => 'cbe']);

    $this->seed(WebsiteConfigSeeder::class);

    $siteConfig = data_get($project->refresh()->settings, 'website_settings.site_config');

    // cafeexpo/icf/cokelatexpo share this one project but each has a distinct
    // baked GA4 id. A single project-level ga4 would force all three sites onto
    // one measurement id (regression), so it is intentionally null here - each
    // app's analytics.client.ts then falls back to its own nuxt.config gtag id.
    expect($siteConfig['analytics'])->toBe([
        'ga4' => null,
        'tiktok_pixel' => null,
    ]);

    // nav/identity ARE byte-identical across the three apps, so those still seed.
    expect($siteConfig['identity']['company_name'])->toBe('PT Panorama Media');
    expect($siteConfig['nav']['header'])->toBeArray()->not->toBeEmpty();
});

it('records the intentional renex/megabuild GA4 collision as-is', function () {
    $project = Project::factory()->create(['username' => 'renex']);

    $this->seed(WebsiteConfigSeeder::class);

    $siteConfig = data_get($project->refresh()->settings, 'website_settings.site_config');

    expect($siteConfig['analytics']['ga4'])->toBe('G-2PJCW7S32V');
});

it('leaves nav unset for campx since its baked nav is genuinely empty', function () {
    $project = Project::factory()->create(['username' => 'campx']);

    $this->seed(WebsiteConfigSeeder::class);

    $siteConfig = data_get($project->refresh()->settings, 'website_settings.site_config');

    expect($siteConfig)->not->toHaveKey('nav');
    expect($siteConfig['identity']['company_name'])->toBe('CampX');
});

it('merges site_config without clobbering other website_settings keys', function () {
    $project = Project::factory()->create([
        'username' => 'megabuild',
        'settings' => [
            'contact_form' => ['enabled' => true],
            'website_settings' => [
                'rundown' => [
                    'show_search_bar' => true,
                ],
                'site_config' => [
                    'appearance' => [
                        'enabled' => true,
                        'baseColor' => 'zinc',
                    ],
                ],
            ],
        ],
    ]);

    $this->seed(WebsiteConfigSeeder::class);

    $project->refresh();

    // Unrelated top-level settings key survives untouched.
    expect(data_get($project->settings, 'contact_form.enabled'))->toBeTrue();

    // Sibling website_settings key survives untouched.
    expect(data_get($project->settings, 'website_settings.rundown.show_search_bar'))->toBeTrue();

    // Pre-existing site_config sub-key not present in the seed data (appearance)
    // survives the merge - only nav/analytics/identity are wholesale-managed.
    expect(data_get($project->settings, 'website_settings.site_config.appearance'))->toBe([
        'enabled' => true,
        'baseColor' => 'zinc',
    ]);

    // The seeded keys did land.
    expect(data_get($project->settings, 'website_settings.site_config.analytics.ga4'))->toBe('G-2PJCW7S32V');
});

it('replaces nav wholesale on re-run instead of merging stale trailing entries', function () {
    $project = Project::factory()->create([
        'username' => 'megabuild',
        'settings' => [
            'website_settings' => [
                'site_config' => [
                    'nav' => [
                        'header' => [
                            ['label' => 'Home', 'path' => '/'],
                            ['label' => 'Stale Extra Link', 'path' => '/stale'],
                            ['label' => 'Another Stale Link', 'path' => '/stale-2'],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $this->seed(WebsiteConfigSeeder::class);

    $header = data_get($project->refresh()->settings, 'website_settings.site_config.nav.header');

    $labels = array_column($header, 'label');

    expect($labels)->not->toContain('Stale Extra Link');
    expect($labels)->not->toContain('Another Stale Link');
});

it('is idempotent and skips projects that do not exist', function () {
    $project = Project::factory()->create(['username' => 'megabuild']);

    $this->seed(WebsiteConfigSeeder::class);
    $first = data_get($project->refresh()->settings, 'website_settings.site_config');

    $this->seed(WebsiteConfigSeeder::class);
    $second = data_get($project->refresh()->settings, 'website_settings.site_config');

    expect($second)->toBe($first);
});

it('logs a warning and does not throw when a seed username has no matching project', function () {
    // No project exists for any seeded username - the seeder must not throw.
    $this->seed(WebsiteConfigSeeder::class);

    expect(Project::query()->count())->toBe(0);
});

it('is wired into the run_website_config_seed migration so it executes on deploy via `migrate`', function () {
    $project = Project::factory()->create(['username' => 'megabuild']);

    $migration = require base_path('database/migrations/2026_07_12_180942_run_website_config_seed.php');
    $migration->up();

    $siteConfig = data_get($project->refresh()->settings, 'website_settings.site_config');

    expect($siteConfig['analytics']['ga4'])->toBe('G-2PJCW7S32V');
    expect($siteConfig['nav']['header'])->toBeArray()->not->toBeEmpty();
});

it('nulls a previously mis-seeded shared cbe GA4 via the corrective migration', function () {
    $project = Project::factory()->create([
        'username' => 'cbe',
        'settings' => [
            'website_settings' => [
                'site_config' => [
                    'analytics' => ['ga4' => 'G-9KLJTWG6QF', 'tiktok_pixel' => null],
                ],
            ],
        ],
    ]);

    $migration = require base_path('database/migrations/2026_07_12_194909_fix_shared_cbe_project_ga4.php');
    $migration->up();

    expect(data_get($project->refresh()->settings, 'website_settings.site_config.analytics.ga4'))->toBeNull();
});

it('leaves a deliberately-edited shared cbe GA4 untouched by the corrective migration', function () {
    $project = Project::factory()->create([
        'username' => 'cbe',
        'settings' => [
            'website_settings' => [
                'site_config' => [
                    'analytics' => ['ga4' => 'G-DELIBERATE1', 'tiktok_pixel' => null],
                ],
            ],
        ],
    ]);

    $migration = require base_path('database/migrations/2026_07_12_194909_fix_shared_cbe_project_ga4.php');
    $migration->up();

    // Only the exact mis-seeded id is reverted; an operator's later choice stays.
    expect(data_get($project->refresh()->settings, 'website_settings.site_config.analytics.ga4'))->toBe('G-DELIBERATE1');
});
