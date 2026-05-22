<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which checkout integration a gateway uses when creating a payment.
     *
     * Defaulting to `payment_link_legacy` keeps every existing row on the
     * current Xendit Invoices API flow with no backfill required.
     */
    public function up(): void
    {
        Schema::table('project_payment_gateways', function (Blueprint $table) {
            $table->string('checkout_method')->default('payment_link_legacy')->after('mode');
        });
    }

    public function down(): void
    {
        Schema::table('project_payment_gateways', function (Blueprint $table) {
            $table->dropColumn('checkout_method');
        });
    }
};
