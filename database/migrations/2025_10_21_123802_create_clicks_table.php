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
        Schema::create('clicks', function (Blueprint $table) {
            $table->id();
            $table->morphs('clickable');
            $table->unsignedBigInteger('clicker_id')->nullable();
            $table->string('link_label')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('referer')->nullable();
            $table->timestamp('clicked_at');
            $table->timestamps();

            $table->foreign('clicker_id')->references('id')->on('users')->onDelete('set null');

            // Composite indexes
            $table->index(['clickable_type', 'clickable_id', 'clicked_at']);
            $table->index(['clickable_type', 'clickable_id', 'clicker_id', 'clicked_at']);

            // Single column indexes
            $table->index('clicked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clicks');
    }
};
