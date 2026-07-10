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
        Schema::table('orders', function (Blueprint $table) {
            // Who placed the order: 'exhibitor' (self-service) or 'staff' (manual
            // order created on the exhibitor's behalf). Explicit column because a
            // user's role can change over time, so created_by alone is not a
            // reliable audit signal.
            $table->string('source', 20)->default('exhibitor')->after('order_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
