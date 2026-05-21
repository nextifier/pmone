<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('provider'); // xendit, midtrans, etc.
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type')->nullable();
            $table->string('external_id')->nullable();
            $table->string('status'); // processed, ignored, rejected, error
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('message')->nullable();
            $table->jsonb('payload')->default('{}');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['project_id', 'provider', 'created_at']);
            $table->index('external_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
    }
};
