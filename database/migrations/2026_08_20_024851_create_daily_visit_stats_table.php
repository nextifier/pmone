<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('daily_visit_stats', function (Blueprint $table) {
            $table->id();
            $table->morphs('visitable');
            $table->date('date');

            // Three measurement methods have written to `visits` over its life and
            // they are not comparable: `server_render` counted SSR renders (bots and
            // prefetchers included, measured at ~5x real traffic), `beacon` counts
            // browsers that ran JavaScript, `ga4` is Google's own browser count used
            // to fill the years before the beacon existed. Keeping the source on the
            // row is what lets one rule decide which one counts for a given date
            // instead of silently adding them together.
            $table->string('source');

            $table->unsignedInteger('views');

            // Null means "not measurable", zero means "measured, and it was zero".
            // Before the event websites forwarded the visitor IP, every beacon
            // arrived from one Cloudflare Worker address, so a distinct count for
            // those dates would read as 1 and look like a bug.
            $table->unsignedInteger('unique_visitors')->nullable();

            $table->timestamps();

            // Named explicitly: the generated name would exceed PostgreSQL's 63
            // character identifier limit and be silently truncated.
            $table->unique(['visitable_type', 'visitable_id', 'date', 'source'], 'dvs_target_date_source_unique');

            // Serves the cross-target reports: "all posts, this date range".
            $table->index(['visitable_type', 'date'], 'dvs_type_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_visit_stats');
    }
};
