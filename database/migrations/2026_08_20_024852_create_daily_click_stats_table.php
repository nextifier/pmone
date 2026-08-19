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
        Schema::create('daily_click_stats', function (Blueprint $table) {
            $table->id();
            $table->morphs('clickable');
            $table->date('date');
            $table->unsignedInteger('clicks');
            $table->timestamps();

            // No `source` column, unlike daily_visit_stats: clicks have always been
            // recorded from the browser, so there is only ever one measurement to
            // keep and nothing to disambiguate.
            $table->unique(['clickable_type', 'clickable_id', 'date'], 'dcs_target_date_unique');
            $table->index(['clickable_type', 'date'], 'dcs_type_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_click_stats');
    }
};
