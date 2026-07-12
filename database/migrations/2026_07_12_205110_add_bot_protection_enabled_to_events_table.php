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
            // Gates server-side Cloudflare Turnstile verification on the
            // public ticket-order endpoint for THIS event only. Default
            // false so expos (which never render the widget) are unaffected.
            $table->boolean('bot_protection_enabled')->default(false)->after('max_tickets_per_buyer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('bot_protection_enabled');
        });
    }
};
