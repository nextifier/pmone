<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_orders', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('order_number', 30)->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('pending_payment');
            $table->string('buyer_name')->nullable();
            $table->string('buyer_email')->nullable();
            $table->string('buyer_phone', 50)->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->string('promo_code_applied')->nullable();
            $table->string('payment_ref')->nullable();
            $table->foreignId('payment_gateway_id')->nullable()->constrained('project_payment_gateways')->nullOnDelete();
            $table->string('xendit_invoice_id')->nullable();
            $table->text('payment_url')->nullable();
            $table->string('payment_channel')->nullable();
            $table->timestamp('payment_expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('magic_link_token', 128)->nullable();
            $table->timestamp('magic_link_expires_at')->nullable();
            $table->string('source', 30)->default('public');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('magic_link_token');
            $table->index('xendit_invoice_id');
            $table->index('payment_expires_at');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_orders');
    }
};
