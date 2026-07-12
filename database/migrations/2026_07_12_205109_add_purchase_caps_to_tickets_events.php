<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Max quantity a single buyer may hold of THIS ticket across all
            // their orders for the event. Null (default) = uncapped, so
            // expos are unaffected.
            $table->integer('max_per_buyer')->nullable()->after('max_quantity');
        });

        Schema::table('events', function (Blueprint $table) {
            // Max total tickets (any ticket type) a single buyer may hold for
            // THIS event across all their orders. Null (default) = uncapped.
            $table->integer('max_tickets_per_buyer')->nullable()->after('tickets_enabled');
        });

        Schema::table('ticket_orders', function (Blueprint $table) {
            // Supports the per-buyer held-quantity lookup in
            // TicketPurchaseService::buyerHeldQuantity(): every order is
            // looked up by event + buyer email, further filtered by status
            // in the query itself.
            $table->index(['event_id', 'buyer_email'], 'ticket_orders_event_buyer_email_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_orders', function (Blueprint $table) {
            $table->dropIndex('ticket_orders_event_buyer_email_index');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('max_tickets_per_buyer');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('max_per_buyer');
        });
    }
};
