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
            $table->integer('duration_seconds')->nullable();
            $table->json('metadata')->nullable(); // Store additional data like property count, errors, etc.
            $table->text('error_message')->nullable();
            $table->string('job_id')->nullable(); // Queue job ID
            $table->timestamps();

            $table->index(['sync_type', 'status', 'created_at']);
            $table->index('ga_property_id');
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
