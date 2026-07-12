<?php

use App\Models\Project;
use Illuminate\Database\Migrations\Migration;
use Spatie\ResponseCache\Facades\ResponseCache;

return new class extends Migration
{
    /**
     * Gives each site in the Cafe & Brasserie Expo family its own dashboard-
     * managed GA4 id.
     *
     * cafeexpo (project cbe), cokelatexpo (project cei) and icf all draw their
     * CONTENT + nav/identity from cbe via dataSourceUsername, but the event
     * website now resolves ANALYTICS from each site's OWN project (see
     * pmone-events website-settings.get.ts). So each project holds its own GA4:
     * cbe = cafeexpo's G-896FDXSRSL, cei = cokelatexpo's G-9KLJTWG6QF, icf =
     * icf's G-YFZVWEFRHF - the ids baked in each app's nuxt.config. This
     * supersedes migration 2026_07_12_194909 (which nulled cbe's shared id).
     *
     * Only fills an empty analytics.ga4 so a value an operator deliberately set
     * in the dashboard is never clobbered. Idempotent.
     */
    public function up(): void
    {
        $targets = [
            'cbe' => 'G-896FDXSRSL',
            'cei' => 'G-9KLJTWG6QF',
            'icf' => 'G-YFZVWEFRHF',
        ];

        $changed = false;

        foreach ($targets as $username => $ga4) {
            $project = Project::query()->where('username', $username)->first();

            if (! $project) {
                continue;
            }

            $settings = $project->settings ?? [];
            $currentGa4 = data_get($settings, 'website_settings.site_config.analytics.ga4');

            if ($currentGa4 !== null && $currentGa4 !== '') {
                continue;
            }

            $analytics = data_get($settings, 'website_settings.site_config.analytics');
            $analytics = is_array($analytics) ? $analytics : [];
            $analytics['ga4'] = $ga4;

            if (! array_key_exists('tiktok_pixel', $analytics)) {
                $analytics['tiktok_pixel'] = null;
            }

            data_set($settings, 'website_settings.site_config.analytics', $analytics);
            $project->settings = $settings;
            $project->save();
            $changed = true;
        }

        if ($changed) {
            ResponseCache::clear();
        }
    }

    /**
     * Backfill only - the seeded ids equal each app's baked config, so there is
     * nothing meaningful to reverse.
     */
    public function down(): void
    {
        //
    }
};
