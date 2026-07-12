<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Opt-in scoping pivot (mirrors `contact_project` / `project_user`): a
     * consumer with zero rows here is unscoped and can read any project,
     * preserving today's behavior. A consumer with one or more rows is
     * restricted to those projects only.
     */
    public function up(): void
    {
        Schema::create('api_consumer_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_consumer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['api_consumer_id', 'project_id']);
            $table->index('project_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_consumer_project');
    }
};
