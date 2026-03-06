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
        Schema::table('events', function (Blueprint $table) {
            $table->dateTime('normal_order_opens_at')->nullable()->after('promotion_post_deadline');
            $table->dateTime('normal_order_closes_at')->nullable()->after('normal_order_opens_at');
            $table->dateTime('onsite_order_opens_at')->nullable()->after('normal_order_closes_at');
            $table->dateTime('onsite_order_closes_at')->nullable()->after('onsite_order_opens_at');
            $table->decimal('onsite_penalty_rate', 5, 2)->default(50.00)->after('onsite_order_closes_at');
            $table->text('badge_vip_info')->nullable()->after('onsite_penalty_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'normal_order_opens_at',
                'normal_order_closes_at',
                'onsite_order_opens_at',
                'onsite_order_closes_at',
                'onsite_penalty_rate',
                'badge_vip_info',
            ]);
        });
    }
};
