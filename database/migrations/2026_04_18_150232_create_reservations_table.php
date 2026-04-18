<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('reservation_number', 30)->unique();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('hotel_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('pending_payment');
            $table->dateTime('payment_expires_at');
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('voucher_sent_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('refunded_at')->nullable();

            $table->string('guest_name');
            $table->string('guest_email');
            $table->string('guest_phone', 50);
            $table->string('guest_identity_type', 20);
            $table->string('guest_identity_number', 100);
            $table->string('guest_nationality', 100)->nullable();
            $table->string('guest_company')->nullable();
            $table->text('guest_address')->nullable();
            $table->text('special_request')->nullable();

            $table->decimal('subtotal_rooms', 14, 2)->default(0);
            $table->decimal('subtotal_transfer', 14, 2)->default(0);
            $table->decimal('surcharge_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('service_charge_amount', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);

            $table->string('xendit_invoice_id')->nullable()->unique();
            $table->text('payment_url')->nullable();
            $table->string('payment_method', 50)->nullable();

            $table->decimal('refund_amount', 14, 2)->nullable();
            $table->string('xendit_refund_id')->nullable();
            $table->text('refund_reason')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->string('magic_link_token', 128)->unique();

            $table->string('source', 30)->default('public_website');
            $table->string('project_username')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('hotel_id');
            $table->index('event_id');
            $table->index('payment_expires_at');
            $table->index('created_at');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
