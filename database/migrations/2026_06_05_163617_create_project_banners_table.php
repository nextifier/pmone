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
        Schema::create('project_banners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('placement')->default('hero');
            $table->string('type')->default('image');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->text('link')->nullable();
            $table->string('cta_label')->nullable();
            $table->string('aspect_ratio')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->dateTime('start_time')->nullable();
            $table->dateTime('end_time')->nullable();
            $table->jsonb('more_details')->nullable();
            $table->jsonb('settings')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'placement', 'sort_order']);
            $table->index('is_active');
            $table->index(['start_time', 'end_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_banners');
    }
};
