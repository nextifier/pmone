<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_rules', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('name');
            $table->string('slug', 120)->unique();
            $table->text('description')->nullable();
            $table->string('kind', 20)->default('discount');
            $table->string('value_type', 40)->default('percentage');
            $table->decimal('value', 14, 4)->default(0);
            $table->jsonb('value_config')->nullable();
            $table->decimal('max_discount_amount', 14, 2)->nullable();
            $table->decimal('min_purchase_amount', 14, 2)->nullable();
            $table->boolean('applies_before_tax')->default(true);
            $table->string('stacking_mode', 30)->default('exclusive');
            $table->smallInteger('priority')->default(100);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->jsonb('target_types')->nullable();
            $table->jsonb('applicability')->nullable();
            $table->string('trigger_type', 40)->default('none');
            $table->jsonb('trigger_config')->nullable();
            $table->boolean('revert_usage_on_cancel')->default(true);
            $table->boolean('is_system_manual')->default(false);
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'kind']);
            $table->index('trigger_type');
            $table->index(['event_id', 'is_active']);
            $table->index(['starts_at', 'ends_at']);
            $table->index('is_system_manual');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_rules');
    }
};
