<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Attendee-level void (refund/cancel a single seat without touching the
     * rest of the order): `cancelled_at` gates check-in + manifest/search,
     * `cancelled_reason` is a free-text staff note, `cancelled_by` audits who
     * voided it.
     */
    public function up(): void
    {
        Schema::table('attendees', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('reprint_count');
            $table->string('cancelled_reason')->nullable()->after('cancelled_at');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_reason')->constrained('users')->nullOnDelete();

            $table->index(['ticket_id', 'cancelled_at']);
        });
    }

    public function down(): void
    {
        Schema::table('attendees', function (Blueprint $table) {
            $table->dropIndex(['ticket_id', 'cancelled_at']);
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancelled_at', 'cancelled_reason']);
        });
    }
};
