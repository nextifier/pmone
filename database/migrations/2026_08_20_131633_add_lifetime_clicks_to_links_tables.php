<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Closes the last two counters still reading a 90-day window. Short links carry
     * the most clicks in the system by a wide margin, and a link shared in a
     * campaign that ended four months ago reported zero.
     */
    public function up(): void
    {
        Schema::table('short_links', function (Blueprint $table) {
            $table->unsignedBigInteger('lifetime_clicks')->default(0);
            $table->index('lifetime_clicks');
        });

        Schema::table('link_page_items', function (Blueprint $table) {
            $table->unsignedBigInteger('lifetime_clicks')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('short_links', function (Blueprint $table) {
            $table->dropIndex(['lifetime_clicks']);
            $table->dropColumn('lifetime_clicks');
        });

        Schema::table('link_page_items', function (Blueprint $table) {
            $table->dropColumn('lifetime_clicks');
        });
    }
};
