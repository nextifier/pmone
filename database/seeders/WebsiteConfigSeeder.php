<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;
use Spatie\ResponseCache\Facades\ResponseCache;

/**
 * One-time backfill of the dashboard-managed `site_config` (nav / analytics /
 * identity) from the CURRENT baked config already live in the pmone-events
 * apps (app.config.ts routes/company + nuxt.config.ts gtag/tiktok ids), so
 * the new Navigation/Analytics/Company Identity editors reflect reality
 * instead of starting blank. See `ProjectController::updateWebsiteSettings`
 * for the authoritative shape/merge contract this mirrors.
 *
 * Content lives in database/seeders/data/website-config-seed.json, keyed by
 * PM One project username. Each entry may hold `nav` ({header,dialog,footer}
 * - any subset), `analytics` ({ga4,tiktok_pixel}) and `identity`
 * ({company_name,company_address}). Entries also carry `_source_apps` /
 * `_notes` documentation keys that this seeder ignores.
 *
 * Several pmone-events apps share one PM One project as their data source
 * (e.g. cafeexpo/icf/cokelatexpo -> "cbe"); the JSON already resolved that
 * collision at extraction time (last app wins - see the `cbe` entry's
 * `_notes`), so this seeder is a straight 1:1 username -> project mapping.
 *
 * Appearance is intentionally NOT seeded: every pmone-events app currently
 * has `appearance.enabled: false` (native app.css palette), so there is no
 * baked appearance config to migrate. Legal pages are intentionally NOT
 * seeded either: the baked legal copy lives in shared Vue templates
 * (layers/base/app/pages/terms.vue etc.) with `{{ companyName }}`
 * interpolation, not extractable as clean HTML - they stay fail-open
 * (empty override = base template renders) until an operator writes a
 * custom override in the dashboard.
 *
 * Idempotent: re-running overwrites `site_config.{nav,analytics,identity}`
 * with the same baked values (no drift) and leaves every other
 * `website_settings` key (rundown, blog, ticket_tabs, terms, ...) untouched,
 * using the same array_replace_recursive + wholesale-replace-nav discipline
 * as the controller. Projects missing locally/in this environment are
 * skipped with a warning rather than failing the run.
 *
 * Run manually: php artisan db:seed --class=WebsiteConfigSeeder
 */
class WebsiteConfigSeeder extends Seeder
{
    private const DATA_PATH = 'database/seeders/data/website-config-seed.json';

    public function run(): void
    {
        $data = $this->load();

        $seeded = 0;

        foreach ($data as $username => $entry) {
            $project = Project::query()->where('username', $username)->first();

            if (! $project) {
                $this->command?->warn("Project not found, skipped: {$username}");

                continue;
            }

            $this->applySiteConfig($project, $entry);
            $seeded++;
        }

        $this->command?->info("Seeded website site_config for {$seeded} project(s).");
    }

    /**
     * @return array<string, array{nav?: array<string, mixed>, analytics?: array<string, mixed>, identity?: array<string, mixed>}>
     */
    private function load(): array
    {
        $path = base_path(self::DATA_PATH);

        if (! is_file($path)) {
            $this->command?->warn('Skipped missing website config seed data: '.self::DATA_PATH);

            return [];
        }

        return json_decode((string) file_get_contents($path), true) ?: [];
    }

    /**
     * Merge this project's baked nav/analytics/identity into
     * `settings.website_settings.site_config`, mirroring
     * `ProjectController::updateWebsiteSettings` exactly: array_replace_recursive
     * for the whole website_settings block, then a wholesale (non-recursive)
     * replace of `site_config.nav` specifically so a shorter baked nav never
     * gets merged index-by-index with a longer stale one.
     *
     * @param  array<string, mixed>  $entry
     */
    private function applySiteConfig(Project $project, array $entry): void
    {
        $siteConfig = array_filter([
            'analytics' => $entry['analytics'] ?? null,
            'identity' => $entry['identity'] ?? null,
        ], fn (mixed $value): bool => $value !== null);

        if (array_key_exists('nav', $entry)) {
            $siteConfig['nav'] = $entry['nav'];
        }

        $settings = $project->settings ?? [];
        $current = data_get($settings, 'website_settings', []);
        $merged = array_replace_recursive($current, ['site_config' => $siteConfig]);

        if (array_key_exists('nav', $siteConfig)) {
            data_set($merged, 'site_config.nav', $siteConfig['nav']);
        }

        data_set($settings, 'website_settings', $merged);

        $project->settings = $settings;
        $project->save();

        // Mirrors ProjectController::updateWebsiteSettings's explicit clear:
        // Project::booted() only busts these tags on `saved` when 'settings'
        // changed, via DB::afterCommit - redundant inside a seeder run (no
        // open transaction), but kept for parity with the controller and as
        // a safety net if this is ever called from within a transaction.
        ResponseCache::clear($project->settingsResponseCacheTags());
    }
}
