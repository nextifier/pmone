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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('name');
            $table->string('job_title')->nullable();
            $table->jsonb('emails')->nullable();
            $table->jsonb('phones')->nullable();
            $table->string('company_name')->nullable();
            $table->string('website')->nullable();
            $table->jsonb('address')->nullable();
            $table->text('notes')->nullable();
            $table->string('source', 50)->nullable();
            $table->jsonb('more_details')->nullable();
            $table->string('status', 50)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index('company_name');
            $table->index('status');
            $table->index('source');
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
