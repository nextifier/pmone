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
        Schema::create('api_consumer_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_consumer_id')->constrained('api_consumers')->cascadeOnDelete();
            $table->string('endpoint');
            $table->string('method', 10);
            $table->integer('status_code')->nullable();
            $table->integer('response_time_ms')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('origin')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Indexes for analytics queries
            $table->index(['api_consumer_id', 'created_at']);
            $table->index('created_at');
            $table->index('endpoint');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_consumer_requests');
    }
};
