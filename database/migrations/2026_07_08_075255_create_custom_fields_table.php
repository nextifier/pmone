<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('fieldable_type');
            $table->unsignedBigInteger('fieldable_id');
            $table->string('context', 40);
            $table->string('type', 50);
            $table->jsonb('label');
            $table->jsonb('placeholder')->nullable();
            $table->jsonb('help_text')->nullable();
            $table->jsonb('options')->nullable();
            $table->jsonb('validation')->nullable();
            $table->jsonb('settings')->nullable();
            $table->string('key', 100)->nullable();
            $table->string('system_key', 50)->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->integer('order_column')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['fieldable_type', 'fieldable_id', 'context', 'order_column'], 'cf_owner_context_order_idx');
            $table->index(['fieldable_type', 'fieldable_id', 'context', 'is_active'], 'cf_owner_context_active_idx');
            $table->index('deleted_at');
        });

        DB::statement('CREATE UNIQUE INDEX cf_key_unique ON custom_fields (fieldable_type, fieldable_id, context, "key") WHERE "key" IS NOT NULL AND deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX cf_system_key_unique ON custom_fields (fieldable_type, fieldable_id, context, system_key) WHERE system_key IS NOT NULL AND deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_fields');
    }
};
