<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Per-event kill switches for the event's PUBLIC website. Default
            // TRUE so every existing event renders exactly as it does today;
            // only an explicit admin flip hides anything.
            //
            // The flag of the event being REQUESTED is the master switch for
            // that site's page: turning FLEI's off empties
            // /brands-with-conjunctions on franchise-expo.co.id INCLUDING the
            // conjunction groups, while leaving each conjunction event's own
            // site alone - that site resolves its own active event, and
            // therefore its own flag.
            //
            // after() is a MySQL-only modifier and a no-op on Postgres; kept
            // for consistency with add_bot_protection_enabled_to_events_table.
            $table->boolean('brands_public_visible')->default(true)->after('waitlist_mode');
            $table->boolean('rundown_public_visible')->default(true)->after('brands_public_visible');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['brands_public_visible', 'rundown_public_visible']);
        });
    }
};
