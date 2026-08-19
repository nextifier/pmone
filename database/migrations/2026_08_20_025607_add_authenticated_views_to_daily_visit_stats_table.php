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
        Schema::table('daily_visit_stats', function (Blueprint $table) {
            // The analytics pages break Total Visits down into signed-in and
            // anonymous. Keeping that split in the rollup is what lets those pages
            // read one source for the whole history instead of falling back to the
            // 90-day raw table for part of the range. Anonymous is the remainder,
            // so it needs no column of its own.
            $table->unsignedInteger('authenticated_views')->default(0)->after('views');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_visit_stats', function (Blueprint $table) {
            $table->dropColumn('authenticated_views');
        });
    }
};
