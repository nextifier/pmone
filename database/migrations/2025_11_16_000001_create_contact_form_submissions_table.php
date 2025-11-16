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
        Schema::create('contact_form_submissions', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            // Form data (dynamic fields)
            $table->json('form_data');

            // Email subject (from frontend)
            $table->string('subject')->nullable();

            // Status tracking (VARCHAR, not ENUM!)
            $table->string('status', 50)->default('new');

            // Follow-up tracking
            $table->timestamp('followed_up_at')->nullable();
            $table->foreignId('followed_up_by')->nullable()->constrained('users');

            // Security & analytics
            $table->string('ip_address', 45)->nullable(); // IPv6 support
            $table->text('user_agent')->nullable();

            $table->timestamps();

            // Indexes for performance
            $table->index('project_id');
            $table->index('status');
            $table->index(['project_id', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_form_submissions');
    }
};
