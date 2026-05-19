<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('code', 60)->unique();
            $table->foreignId('promotion_rule_id')->constrained()->cascadeOnDelete();
            $table->integer('usage_limit')->nullable();
            $table->smallInteger('usage_limit_per_email')->nullable()->default(1);
            $table->integer('usage_count')->default(0);
            $table->dateTime('valid_from')->nullable();
            $table->dateTime('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('issued_to_email')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'valid_from', 'valid_until']);
            $table->index('promotion_rule_id');
            $table->index('event_id');
            $table->index('issued_to_email');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};
