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
        Schema::create('promotion_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_event_id')->constrained('brand_event')->cascadeOnDelete();
            $table->text('caption')->nullable();
            $table->jsonb('custom_fields')->nullable();
            $table->integer('order_column')->nullable();
            $table->timestamps();

            $table->index('brand_event_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_posts');
    }
};
