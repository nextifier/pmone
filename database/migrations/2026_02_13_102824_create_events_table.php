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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->integer('edition_number')->nullable();
            $table->text('description')->nullable();
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->string('location', 500)->nullable();
            $table->string('location_link', 1000)->nullable();
            $table->string('hall')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('visibility', 20)->default('private');
            $table->jsonb('settings')->nullable();
            $table->jsonb('custom_fields')->nullable();
            $table->text('order_form_content')->nullable();
            $table->dateTime('order_form_deadline')->nullable();
            $table->dateTime('promotion_post_deadline')->nullable();
            $table->decimal('gross_area', 10, 2)->nullable();
            $table->boolean('is_active')->default(false);
            $table->integer('order_column')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Unique slug per project (partial index for non-deleted)
            $table->unique(['project_id', 'slug']);

            // Indexes
            $table->index(['project_id', 'status']);
            $table->index('start_date');
            $table->index('order_column');
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
