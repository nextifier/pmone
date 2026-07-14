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
        Schema::table('brand_event', function (Blueprint $table) {
            // Manual override for this exhibitor's billing currency. Null = auto-resolve
            // from the brand's country. Valid values: IDR, USD.
            $table->string('currency_override', 3)->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('brand_event', function (Blueprint $table) {
            $table->dropColumn('currency_override');
        });
    }
};
