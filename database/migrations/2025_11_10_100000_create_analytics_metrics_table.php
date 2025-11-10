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
        Schema::create('analytics_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('property_id')->nullable()->index();
            $table->string('metric_type')->index(); // api_call, cache_hit, cache_miss, quota_usage
            $table->integer('metric_value'); // duration_ms, count, tokens, etc
            $table->json('metadata')->nullable(); // Additional context
            $table->timestamps();

            // Indexes for common queries
            $table->index(['metric_type', 'created_at']);
            $table->index(['property_id', 'metric_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_metrics');
    }
};
