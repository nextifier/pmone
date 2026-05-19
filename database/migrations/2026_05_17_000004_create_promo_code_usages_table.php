<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_code_usages', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('promo_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('applied_adjustment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('adjustable_type', 100);
            $table->unsignedBigInteger('adjustable_id');
            $table->string('email');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount_discounted', 14, 2)->default(0);
            $table->dateTime('voided_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['promo_code_id', 'email']);
            $table->index(['adjustable_type', 'adjustable_id']);
            $table->index('email');
            $table->index('voided_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_code_usages');
    }
};
