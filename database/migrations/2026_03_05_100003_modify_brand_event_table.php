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
        Schema::table('brand_event', function (Blueprint $table) {
            $table->dropUnique(['brand_id', 'event_id']);
        });

        Schema::table('brand_event', function (Blueprint $table) {
            $table->string('fascia_name')->nullable()->after('booth_price');
            $table->string('badge_name')->nullable()->after('fascia_name');

            $table->index('brand_id');
            $table->index(['event_id', 'booth_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('brand_event', function (Blueprint $table) {
            $table->dropColumn(['fascia_name', 'badge_name']);
            $table->dropIndex(['brand_id']);
            $table->dropIndex(['event_id', 'booth_number']);
        });

        Schema::table('brand_event', function (Blueprint $table) {
            $table->unique(['brand_id', 'event_id']);
        });
    }
};
