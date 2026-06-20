<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_orders', function (Blueprint $table) {
            // Admin "Bulk Generate" batches: a human label + async generation
            // status (null for normal public orders).
            $table->string('batch_label')->nullable()->after('source');
            $table->string('batch_status', 20)->nullable()->after('batch_label');
            $table->index(['event_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::table('ticket_orders', function (Blueprint $table) {
            $table->dropIndex(['event_id', 'source']);
            $table->dropColumn(['batch_label', 'batch_status']);
        });
    }
};
