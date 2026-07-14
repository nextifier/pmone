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
        Schema::table('event_products', function (Blueprint $table) {
            // Manually-entered USD price shown to exhibitors billed in USD. No
            // auto-conversion: null means the product is not offered in USD.
            $table->decimal('price_usd', 15, 2)->nullable()->after('price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_products', function (Blueprint $table) {
            $table->dropColumn('price_usd');
        });
    }
};
