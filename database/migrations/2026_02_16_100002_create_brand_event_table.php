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
        Schema::create('brand_event', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('booth_number', 50)->nullable();
            $table->decimal('booth_size', 8, 2)->nullable();
            $table->string('booth_type', 50)->nullable();
            $table->decimal('booth_price', 15, 2)->nullable();
            $table->foreignId('sales_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('promotion_post_limit')->default(1);
            $table->jsonb('custom_fields')->nullable();
            $table->integer('order_column')->nullable();
            $table->timestamps();

            $table->unique(['brand_id', 'event_id']);
            $table->index(['event_id', 'status']);
            $table->index('booth_number');
            $table->index('sales_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_event');
    }
};
