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
        // Indexes for analytics_sync_logs table
        Schema::table('analytics_sync_logs', function (Blueprint $table) {
            // Composite index for filtering by type and status (common query pattern)
            $table->index(['sync_type', 'status'], 'idx_sync_logs_type_status');

            // Index for created_at DESC ordering (for recent logs)
            $table->index('created_at', 'idx_sync_logs_created_at');

            // Composite index for property-specific queries
            $table->index(['ga_property_id', 'status'], 'idx_sync_logs_property_status');

            // Index for job_id lookups
            $table->index('job_id', 'idx_sync_logs_job_id');
        });

        // Indexes for ga_properties table
        Schema::table('ga_properties', function (Blueprint $table) {
            // Composite index for active properties ordered by last sync
            $table->index(['is_active', 'last_synced_at'], 'idx_ga_properties_active_sync');

            // Index for sync frequency queries
            $table->index('sync_frequency', 'idx_ga_properties_sync_frequency');

            // Index for next_sync_at (virtual column, if supported)
            // Note: This is a computed column, so we can't index it directly
            // But we can index last_synced_at and sync_frequency which are used to calculate it
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analytics_sync_logs', function (Blueprint $table) {
            $table->dropIndex('idx_sync_logs_type_status');
            $table->dropIndex('idx_sync_logs_created_at');
            $table->dropIndex('idx_sync_logs_property_status');
            $table->dropIndex('idx_sync_logs_job_id');
        });

        Schema::table('ga_properties', function (Blueprint $table) {
            $table->dropIndex('idx_ga_properties_active_sync');
            $table->dropIndex('idx_ga_properties_sync_frequency');
        });
    }
};
