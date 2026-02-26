<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_conjunctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conjunction_event_id')->constrained('events')->cascadeOnDelete();
            $table->string('conjunction_label')->nullable();
            $table->integer('order_column')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'conjunction_event_id']);
            $table->index('conjunction_event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_conjunctions');
    }
};
