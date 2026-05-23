<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Short-lived Xendit Components SDK key for COMPONENTS-mode sessions.
     *
     * Persisted so the magic-link GET endpoint can serve the same key on
     * every page load WITHOUT minting a fresh session each time (the
     * Sessions API rejects creating a second session for the same
     * `reference_id`). The key is cleared on payment / expiry by the
     * webhook handler.
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->text('components_sdk_key')->nullable()->after('xendit_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('components_sdk_key');
        });
    }
};
