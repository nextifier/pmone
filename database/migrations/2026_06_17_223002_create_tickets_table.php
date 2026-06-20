<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('kind')->default('entry');
            $table->json('title');
            $table->string('tier')->nullable();
            $table->json('benefits')->nullable();
            $table->string('currency', 3)->default('IDR');
            $table->string('purchase_type')->default('first_party');
            $table->string('external_url')->nullable();
            $table->json('more_details')->nullable();
            $table->boolean('print_on_redeem')->default(false);
            $table->integer('stock')->nullable();
            $table->integer('sold_count')->default(0);
            $table->integer('min_quantity')->default(1);
            $table->integer('max_quantity')->nullable();
            $table->jsonb('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('order_column')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['event_id', 'slug']);
            $table->index(['event_id', 'is_active']);
            $table->index(['event_id', 'kind']);
            $table->index(['event_id', 'order_column']);
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
