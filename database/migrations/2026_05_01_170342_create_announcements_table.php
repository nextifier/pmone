<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('type', 20)->default('info');
            $table->string('status', 20)->default('draft');
            $table->boolean('is_global')->default(false);
            $table->jsonb('target_roles')->nullable();
            $table->jsonb('cta_actions')->nullable();
            $table->jsonb('more_details')->nullable();
            $table->jsonb('settings')->nullable();
            $table->dateTime('start_time')->nullable();
            $table->dateTime('end_time')->nullable();
            $table->boolean('is_dismissible')->default(true);
            $table->integer('order_column')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('start_time');
            $table->index('end_time');
            $table->index('order_column');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
