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
        Schema::table('promotion_rules', function (Blueprint $table) {
            // Currency the rule applies to. Null = currency-agnostic (only valid for
            // pure-percentage rules without nominal thresholds); otherwise the rule is
            // only valid for orders sharing this currency.
            $table->string('currency', 3)->nullable()->after('value_config');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promotion_rules', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
