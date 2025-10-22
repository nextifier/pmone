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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->string('name');
            $table->string('username')->unique();
            $table->text('bio')->nullable();
            $table->json('settings')->default('{}');
            $table->json('more_details')->default('{}');
            $table->enum('status', ['draft', 'active', 'archived'])->default('active');
            $table->enum('visibility', ['public', 'private', 'members_only'])->default('public');
            $table->string('email')->nullable();
            $table->json('phone')->nullable();
            $table->integer('order_column')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->foreignId('deleted_by')->nullable()->constrained('users');

            $table->index(['username', 'status']);
            $table->index('visibility');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
