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
        Schema::create('post_autosaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->nullable()->constrained('posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->enum('content_format', ['html', 'markdown', 'lexical'])->default('html');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->enum('status', ['draft', 'published', 'scheduled', 'archived'])->default('draft');
            $table->enum('visibility', ['public', 'private', 'members_only'])->default('public');
            $table->timestamp('published_at')->nullable();
            $table->boolean('featured')->default(false);
            $table->integer('reading_time')->nullable();
            $table->json('settings')->default('{}');
            $table->json('tmp_media')->nullable(); // Store temporary media info (featured_image, og_image, etc)
            $table->json('tags')->nullable(); // Store tags as array
            $table->json('authors')->nullable(); // Store authors as array
            $table->timestamps();

            // Composite unique index - one autosave per user per post (null for new posts)
            $table->unique(['post_id', 'user_id']);

            // Index for quick lookup
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_autosaves');
    }
};
