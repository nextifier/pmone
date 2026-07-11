<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marker for a genuine paid event that arrived after the order was already
     * flipped to Expired (a slow bank transfer / retail settlement landing
     * after the 15-min hard-expiry job released the seat) while the stock it
     * needs is no longer available. Set only on the no-oversell "needs
     * reconciliation" path — a resurrect back to Confirmed leaves this null.
     */
    public function up(): void
    {
        Schema::table('ticket_orders', function (Blueprint $table) {
            $table->timestamp('paid_after_expiry_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_orders', function (Blueprint $table) {
            $table->dropColumn('paid_after_expiry_at');
        });
    }
};
