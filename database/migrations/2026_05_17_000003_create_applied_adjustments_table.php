<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applied_adjustments', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->morphs('adjustable');
            $table->foreignId('promotion_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('promo_code_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20);
            $table->string('label');
            $table->string('value_type', 40);
            $table->decimal('value', 14, 4);
            $table->jsonb('value_config')->nullable();
            $table->decimal('base_amount', 14, 2);
            $table->decimal('amount', 14, 2);
            $table->jsonb('line_breakdown')->nullable();
            $table->jsonb('rule_snapshot')->nullable();
            $table->string('applied_by', 40);
            $table->dateTime('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->timestamps();

            $table->index('promotion_rule_id');
            $table->index('promo_code_id');
            $table->index(['kind', 'voided_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applied_adjustments');
    }
};
