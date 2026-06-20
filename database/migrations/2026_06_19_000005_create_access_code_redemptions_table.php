<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_code_redemptions', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('access_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('applied_adjustment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable();
            $table->dateTime('redeemed_at')->nullable();
            $table->dateTime('voided_at')->nullable();
            $table->timestamps();

            $table->index('access_code_id');
            $table->index('ticket_order_id');
            $table->index('email');
            $table->index(['access_code_id', 'voided_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_code_redemptions');
    }
};
