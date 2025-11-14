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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->enum('content_format', ['html', 'markdown', 'lexical'])->default('html');
            $table->string('featured_image')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('og_image')->nullable();
            $table->string('og_type')->default('article');
            $table->enum('status', ['draft', 'published', 'scheduled', 'archived'])->default('draft');
            $table->enum('visibility', ['public', 'private', 'members_only'])->default('public');
            $table->timestamp('published_at')->nullable();
            $table->boolean('featured')->default(false);
            $table->integer('reading_time')->nullable();
            $table->unsignedBigInteger('view_count')->default(0);
            $table->json('settings')->default('{}');
            $table->enum('source', ['native', 'ghost', 'canvas'])->default('native');
            $table->string('source_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->foreignId('deleted_by')->nullable()->constrained('users');

            // Composite indexes
            $table->index(['slug', 'status']);
            $table->index(['status', 'visibility', 'published_at']);
            $table->index(['source', 'source_id']);
            $table->index(['created_by', 'created_at']);

            // Single column indexes
            $table->index('status');
            $table->index('visibility');
            $table->index('published_at');
            $table->index('featured');
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
        Schema::dropIfExists('posts');
    }
};
