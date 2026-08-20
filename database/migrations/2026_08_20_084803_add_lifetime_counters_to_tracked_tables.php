<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Same read cache as posts.lifetime_views, for the other three things people
     * look up view counts for. Each was reading a count over `visits`, which is
     * pruned to 90 days, so a banner sold on a three-month campaign reported only
     * the tail of it and the number shrank on its own afterwards.
     *
     * Recomputed nightly by visits:rollup from daily_visit_stats, never
     * incremented, so a wrong value heals on the next run.
     */
    public function up(): void
    {
        Schema::table('link_pages', function (Blueprint $table) {
            $table->unsignedBigInteger('lifetime_views')->default(0);
            // Sums the clicks on this page's items. `clicks` has never held a single
            // row for LinkPage itself, so the old page-level count was always zero.
            $table->unsignedBigInteger('lifetime_clicks')->default(0);
            $table->index('lifetime_views');
        });

        Schema::table('brand_event', function (Blueprint $table) {
            $table->unsignedBigInteger('lifetime_views')->default(0);
            $table->unsignedBigInteger('lifetime_clicks')->default(0);
            $table->index('lifetime_views');
        });

        Schema::table('project_banners', function (Blueprint $table) {
            // Impressions, in the banner's own vocabulary: the relation is called
            // impressions() but writes to the same morph table as every other visit.
            $table->unsignedBigInteger('lifetime_impressions')->default(0);
            $table->unsignedBigInteger('lifetime_clicks')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('link_pages', function (Blueprint $table) {
            $table->dropIndex(['lifetime_views']);
            $table->dropColumn(['lifetime_views', 'lifetime_clicks']);
        });

        Schema::table('brand_event', function (Blueprint $table) {
            $table->dropIndex(['lifetime_views']);
            $table->dropColumn(['lifetime_views', 'lifetime_clicks']);
        });

        Schema::table('project_banners', function (Blueprint $table) {
            $table->dropColumn(['lifetime_impressions', 'lifetime_clicks']);
        });
    }
};
