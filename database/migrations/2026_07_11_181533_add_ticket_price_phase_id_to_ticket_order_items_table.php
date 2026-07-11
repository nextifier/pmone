<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The price phase this line was actually charged at, so expiry/refund can
     * release the exact phase's sold_count instead of matching on the
     * free-text phase_label. Nullable: historical rows before this column
     * predate phase-level release and are left ungated (no backfill).
     */
    public function up(): void
    {
        Schema::table('ticket_order_items', function (Blueprint $table) {
            $table->foreignId('ticket_price_phase_id')->nullable()->after('phase_label')
                ->constrained('ticket_price_phases')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ticket_price_phase_id');
        });
    }
};
