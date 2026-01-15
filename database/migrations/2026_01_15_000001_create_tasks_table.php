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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 50)->nullable()->default('todo');
            $table->string('priority', 50)->nullable();
            $table->string('complexity', 50)->nullable();
            $table->string('visibility', 50)->default('private');

            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('estimated_start_at')->nullable();
            $table->timestamp('estimated_completion_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->integer('order_column')->default(0);

            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->foreignId('deleted_by')->nullable()->constrained('users');

            $table->timestamps();
            $table->softDeletes();

            // Composite indexes
            $table->index(['status', 'visibility']);
            $table->index(['assignee_id', 'status']);
            $table->index(['project_id', 'status']);
            $table->index(['created_by', 'created_at']);

            // Single column indexes
            $table->index('status');
            $table->index('priority');
            $table->index('complexity');
            $table->index('visibility');
            $table->index('estimated_completion_at');
            $table->index('order_column');
            $table->index('deleted_at');
            $table->index('created_by');
            $table->index('updated_by');
        });

        Schema::create('task_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 50)->default('viewer');
            $table->timestamps();

            $table->unique(['task_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_user');
        Schema::dropIfExists('tasks');
    }
};
