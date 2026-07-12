<?php

use App\Models\Project;
use Illuminate\Database\Migrations\Migration;
use Spatie\ResponseCache\Facades\ResponseCache;

return new class extends Migration
{
    /**
     * Corrects the shared `cbe` project's seeded GA4 id.
     *
     * cafeexpo, icf and cokelatexpo are three distinct websites that all read
     * their website-settings from this one PM One project (dataSourceUsername
     * =cbe). The initial WebsiteConfigSeeder (migration 2026_07_12_180942)
     * collapsed their three DIFFERENT baked GA4 ids into cokelatexpo's
     * (G-9KLJTWG6QF), which - once analytics.client.ts prefers site_config over
     * the baked nuxt.config gtag id - would silently redirect cafeexpo's
     * (G-896FDXSRSL) and icf's (G-YFZVWEFRHF) analytics to cokelatexpo's
     * property. A single project-level ga4 cannot represent three sites, so we
     * null it out; each app's plugin then falls back to its own baked id
     * (fail-open), restoring correct per-site tracking.
     *
     * Guarded on the exact mis-seeded value so a deliberate later dashboard
     * edit is never clobbered. Idempotent: a no-op once ga4 is already null.
     */
    public function up(): void
    {
        $project = Project::query()->where('username', 'cbe')->first();

        if (! $project) {
            return;
        }

        $settings = $project->settings ?? [];

        if (data_get($settings, 'website_settings.site_config.analytics.ga4') !== 'G-9KLJTWG6QF') {
            return;
        }

        data_set($settings, 'website_settings.site_config.analytics.ga4', null);
        $project->settings = $settings;
        $project->save();

        ResponseCache::clear();
    }

    /**
     * Backfill correction only - re-seeding the wrong shared id would just
     * reintroduce the regression, so there is nothing to reverse.
     */
    public function down(): void
    {
        //
    }
};
