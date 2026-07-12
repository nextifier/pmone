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
        Schema::table('events', function (Blueprint $table) {
            // 'auto_offer' | 'notify_only' (App\Enums\Ticketing\WaitlistMode).
            // Defaults to auto_offer so existing events behave like Plan 020's
            // primary option (hold the seat) with no config needed.
            $table->string('waitlist_mode')->default('auto_offer')->after('business_matching_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('waitlist_mode');
        });
    }
};
