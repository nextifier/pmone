<?php

use App\Models\Event;
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
            $table->boolean('business_matching_enabled')->default(false)->after('tickets_enabled');
        });

        // Preserve existing behaviour: events that already have intake fields were
        // effectively running Business Matching, so turn it on for them.
        Event::query()
            ->whereHas('eventCustomFields', fn ($q) => $q->where('is_active', true))
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
