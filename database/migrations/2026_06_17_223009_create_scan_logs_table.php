<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendee_id')->constrained()->cascadeOnDelete();
            $table->string('action', 30);
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scanned_at');
            $table->string('idempotency_key')->unique();
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->index('attendee_id');
            $table->index(['event_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_logs');
    }
};
