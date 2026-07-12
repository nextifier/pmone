<?php

use Database\Seeders\WebsiteConfigSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\ResponseCache\Facades\ResponseCache;

return new class extends Migration
{
    /**
     * Runs the one-time WebsiteConfigSeeder as part of deploy migrations,
     * since the production deploy pipeline runs `php artisan migrate` but
     * not `db:seed`. Backfills `settings.website_settings.site_config`
     * (nav/analytics/identity) for the known projects from the baked
     * pmone-events app configs, so the dashboard's Navigation/Analytics/
     * Company Identity editors reflect reality instead of starting blank.
     * See WebsiteConfigSeeder for the full data source/idempotency contract.
     *
     * The seeder warns-and-skips any project missing in a given
     * environment rather than throwing, so a partial project list never
     * aborts this migration. Any other exception (e.g. a genuine DB
     * failure) is intentionally allowed to propagate and fail the
     * migration normally rather than being swallowed here.
     */
    public function up(): void
    {
        (new WebsiteConfigSeeder)->run();

        // The seeder already busts each seeded project's own settings
        // tags; clear everything on top so every cached admin + public
        // response (e.g. sitemap, event listings) reflects the new
        // site_config immediately after this migration runs on deploy.
        ResponseCache::clear();
    }

    /**
     * Backfill only: the seeded values equal the current baked config, so
     * there is nothing meaningful to reverse. Reversing would just leave
     * the previously-blank site_config in place until the seeder is
     * re-run manually.
     */
    public function down(): void
    {
        //
    }
};
