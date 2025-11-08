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
        Schema::create('ga_properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('name');
            $table->string('property_id')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->integer('sync_frequency')->default(10)->comment('Sync frequency in minutes');
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->foreignId('deleted_by')->nullable()->constrained('users');

            // Composite index for active properties ordered by last sync
            $table->index(['is_active', 'last_synced_at'], 'idx_ga_properties_active_sync');

            // Index for sync frequency queries
            $table->index('sync_frequency', 'idx_ga_properties_sync_frequency');

            // Single column indexes
            $table->index('project_id');
            $table->index('is_active');
            $table->index('last_synced_at');
            $table->index('deleted_at');
            $table->index('created_by');
            $table->index('updated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ga_properties');
    }
};
