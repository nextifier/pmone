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
            $table->renameColumn('status', 'operational_status');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_status', 20)->default('not_invoiced')->after('operational_status');
            $table->text('cancellation_reason')->nullable()->after('payment_status');
            $table->string('order_period', 20)->nullable()->after('cancellation_reason');
            $table->decimal('applied_penalty_rate', 5, 2)->nullable()->after('order_period');

            $table->index('operational_status');
            $table->index('payment_status');
        });

        // Drop old status index if exists
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['operational_status']);
            $table->dropIndex(['payment_status']);
            $table->dropColumn(['payment_status', 'cancellation_reason', 'order_period', 'applied_penalty_rate']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('operational_status', 'status');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index('status');
        });
    }
};
