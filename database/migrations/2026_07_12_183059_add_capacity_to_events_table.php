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
            // Total-headcount cap across ALL ticket types (fire-code / venue
            // limit), independent of per-ticket stock / per-phase quota /
            // per-session capacity. Null = uncapped.
            $table->integer('capacity')->nullable()->after('onsite_penalty_rate');
            $table->integer('reserved_count')->default(0)->after('capacity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['capacity', 'reserved_count']);
        });
    }
};
