<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('provider'); // xendit, midtrans, etc.
            $table->string('label')->nullable();
            $table->string('mode')->default('live'); // live, test
            $table->boolean('is_active')->default(true);
            $table->text('secret_key')->nullable();
            $table->text('public_key')->nullable();
            $table->text('webhook_token')->nullable();
            $table->jsonb('config')->default('{}');
            $table->timestamp('last_used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'provider', 'is_active']);
            $table->unique(['project_id', 'provider', 'mode', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_payment_gateways');
    }
};
