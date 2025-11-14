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
        Schema::create('post_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->integer('revision_number')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('created_at');

            // Indexes
            $table->index(['post_id', 'created_at']);
            $table->index('revision_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_revisions');
    }
};
