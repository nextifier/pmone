<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_codes', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('code', 60)->unique();
            $table->string('kind', 20);
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('access_code_batches')->nullOnDelete();
            $table->integer('max_uses')->nullable();
            $table->integer('used_count')->default(0);
            $table->dateTime('valid_from')->nullable();
            $table->dateTime('valid_until')->nullable();
            $table->string('bind_email')->nullable();
            $table->string('bind_phone')->nullable();
            $table->string('price_effect', 20)->default('none');
            $table->decimal('price_value', 14, 2)->nullable();
            $table->boolean('stackable')->default(false);
            $table->smallInteger('max_qty_per_redemption')->default(1);
            $table->string('status', 20)->default('active');
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'status']);
            $table->index(['status', 'valid_from', 'valid_until']);
            $table->index('batch_id');
            $table->index('bind_email');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_codes');
    }
};
