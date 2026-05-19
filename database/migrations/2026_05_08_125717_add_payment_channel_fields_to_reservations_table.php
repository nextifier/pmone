<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('payment_channel', 50)->nullable()->after('payment_method');
            $table->string('payment_destination', 100)->nullable()->after('payment_channel');
            $table->string('xendit_payment_id', 100)->nullable()->after('xendit_invoice_id');

            $table->index('payment_channel');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex(['payment_channel']);
            $table->dropColumn(['payment_channel', 'payment_destination', 'xendit_payment_id']);
        });
    }
};
