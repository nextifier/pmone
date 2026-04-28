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
        Schema::create('rundown_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->date('date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->json('title')->nullable();
            $table->json('subtitle')->nullable();
            $table->json('description')->nullable();
            $table->json('theme')->nullable();
            $table->json('location')->nullable();
            $table->json('presented_by')->nullable();
            $table->json('moderator')->nullable();
            $table->jsonb('panelists')->nullable();
            $table->jsonb('speakers')->nullable();
            $table->jsonb('settings')->nullable();
            $table->jsonb('more_details')->nullable();
            $table->integer('order_column')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'is_active']);
            $table->index(['event_id', 'date', 'order_column']);
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rundown_items');
    }
};
