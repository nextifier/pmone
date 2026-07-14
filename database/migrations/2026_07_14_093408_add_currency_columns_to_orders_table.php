<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Transaction currency of the order. Nominal columns (subtotal, tax_amount,
            // total, order_items.unit_price) are denominated in this currency.
            $table->string('currency', 3)->default('IDR')->after('total');
            // FX snapshot taken once at write time; never refreshed. IDR orders use 1.
            $table->decimal('exchange_rate_to_idr', 18, 6)->default(1)->after('currency');
            // Reporting-currency (IDR) equivalent of `total`, frozen at write time.
            // All cross-currency analytics, sort, and filter run on this column.
            $table->decimal('total_idr', 18, 2)->default(0)->after('exchange_rate_to_idr');

            $table->index('total_idr');
        });

        // Backfill legacy orders: currency=IDR + rate=1 already applied via column
        // defaults, so the IDR equivalent equals the stored total.
        DB::table('orders')->update(['total_idr' => DB::raw('total')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['total_idr']);
            $table->dropColumn(['currency', 'exchange_rate_to_idr', 'total_idr']);
        });
    }
};
