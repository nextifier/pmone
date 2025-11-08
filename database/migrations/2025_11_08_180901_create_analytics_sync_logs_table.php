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
        Schema::create('analytics_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('sync_type'); // 'property' or 'aggregate'
            $table->foreignId('ga_property_id')->nullable()->constrained()->onDelete('cascade');
            $table->integer('days')->default(30);
            $table->string('status'); // 'started', 'success', 'failed'
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('duration_seconds', 10, 2)->nullable();
            $table->json('metadata')->nullable(); // Store additional data like property count, errors, etc.
            $table->text('error_message')->nullable();
            $table->string('job_id')->nullable(); // Queue job ID
            $table->timestamps();

            $table->index(['sync_type', 'status', 'created_at']);
            $table->index('ga_property_id');

            // Composite index for filtering by type and status (common query pattern)
            $table->index(['sync_type', 'status'], 'idx_sync_logs_type_status');

            // Index for created_at DESC ordering (for recent logs)
            $table->index('created_at', 'idx_sync_logs_created_at');

            // Composite index for property-specific queries
            $table->index(['ga_property_id', 'status'], 'idx_sync_logs_property_status');

            // Index for job_id lookups
            $table->index('job_id', 'idx_sync_logs_job_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_sync_logs');
    }
};
