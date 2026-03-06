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
        Schema::create('event_document_submissions', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('event_document_id')->constrained('event_documents')->cascadeOnDelete();
            $table->string('booth_identifier', 100);
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->timestamp('agreed_at')->nullable();
            $table->text('text_value')->nullable();
            $table->unsignedInteger('document_version')->default(1);
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('submitted_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->unique(['event_document_id', 'booth_identifier', 'event_id'], 'eds_document_booth_event_unique');
            $table->index(['event_id', 'booth_identifier']);
            $table->index('submitted_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_document_submissions');
    }
};
