<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // When true, an entry ticket valid on multiple days lets the buyer
            // pick which single day at purchase (a "Day Pass"); when false the
            // ticket is valid on all its valid_days (a multi-day bundle).
            $table->boolean('requires_day_selection')->default(false)->after('print_on_redeem');
        });

        Schema::table('ticket_order_items', function (Blueprint $table) {
            // The day the buyer chose for a day-selectable entry ticket.
            $table->foreignId('selected_event_day_id')->nullable()->after('ticket_session_id')
                ->constrained('event_days')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('selected_event_day_id');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('requires_day_selection');
        });
    }
};
