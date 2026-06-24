<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit marker for orders confirmed manually by staff (e.g. when a payment
     * succeeded but the gateway webhook never synced the status). Kept separate
     * from `payment_channel` so the real channel still drives the logo column.
     */
    public function up(): void
    {
        Schema::table('ticket_orders', function (Blueprint $table) {
            $table->timestamp('marked_paid_manually_at')->nullable()->after('paid_at');
            $table->foreignId('marked_paid_by')->nullable()->after('marked_paid_manually_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('marked_paid_by');
            $table->dropColumn('marked_paid_manually_at');
        });
    }
};
