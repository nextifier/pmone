<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->json('title')->nullable();
            $table->json('bio')->nullable();
            $table->string('organization')->nullable();
            $table->jsonb('more_details')->nullable();
            $table->jsonb('settings')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('visibility', 20)->default('public');
            $table->boolean('is_featured')->default(false);
            $table->integer('order_column')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['event_id', 'slug']);
            $table->index(['event_id', 'status', 'is_featured']);
            $table->index(['event_id', 'order_column']);
            $table->index('name');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guests');
    }
};
