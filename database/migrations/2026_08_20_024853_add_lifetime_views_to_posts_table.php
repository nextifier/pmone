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
        Schema::table('posts', function (Blueprint $table) {
            // A read cache over daily_visit_stats, recomputed by visits:rollup rather
            // than incremented, so a wrong value heals on the next run. It exists
            // because the posts table is listed and sorted by this number, and
            // aggregating the rollup on every page load gets heavier every year.
            $table->unsignedBigInteger('lifetime_views')->default(0)->after('published_at');
            $table->index('lifetime_views');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['lifetime_views']);
            $table->dropColumn('lifetime_views');
        });
    }
};
