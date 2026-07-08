<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('business_matching_enabled')->default(false)->after('tickets_enabled');
        });

        // Preserve existing behaviour: events that already have intake fields were
        // effectively running Business Matching, so turn it on for them. Queries
        // the legacy event_custom_fields table directly: the live Event relation
        // now targets the centralized custom_fields table, which does not exist
        // yet at this point in the migration order.
        DB::table('events')
            ->whereIn('id', DB::table('event_custom_fields')->where('is_active', true)->pluck('event_id'))
            ->update(['business_matching_enabled' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('business_matching_enabled');
        });
    }
};
