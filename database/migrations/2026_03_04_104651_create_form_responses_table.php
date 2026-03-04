<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_responses', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->json('response_data');
            $table->string('respondent_email')->nullable();
            $table->string('browser_fingerprint')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('status')->default('new');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['form_id', 'respondent_email']);
            $table->index(['form_id', 'browser_fingerprint']);
            $table->index(['form_id', 'status']);
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_responses');
    }
};
