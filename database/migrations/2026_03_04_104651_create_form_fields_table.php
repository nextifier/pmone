<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('label');
            $table->string('placeholder')->nullable();
            $table->text('help_text')->nullable();
            $table->json('options')->nullable();
            $table->json('validation')->nullable();
            $table->json('settings')->nullable();
            $table->integer('order_column')->default(0);
            $table->timestamps();

            $table->index(['form_id', 'order_column']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_fields');
    }
};
