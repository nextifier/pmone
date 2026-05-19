<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (! Schema::hasColumn('reservations', 'penalty_amount')) {
                $table->decimal('penalty_amount', 14, 2)->default(0)->after('surcharge_amount');
            }
            if (! Schema::hasColumn('reservations', 'promo_code_applied')) {
                $table->string('promo_code_applied', 60)->nullable()->after('discount_amount');
                $table->index('promo_code_applied');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'penalty_amount')) {
                $table->decimal('penalty_amount', 15, 2)->default(0)->after('discount_amount');
            }
            if (! Schema::hasColumn('orders', 'promo_code_applied')) {
                $table->string('promo_code_applied', 60)->nullable()->after('penalty_amount');
                $table->index('promo_code_applied');
            }
        });

        $legacyOrderColumns = array_values(array_filter(
            ['discount_type', 'discount_value', 'applied_penalty_rate'],
            fn (string $col): bool => Schema::hasColumn('orders', $col)
        ));

        if (! empty($legacyOrderColumns)) {
            Schema::table('orders', function (Blueprint $table) use ($legacyOrderColumns) {
                $table->dropColumn($legacyOrderColumns);
            });
        }

        // Normalize orders.discount_amount: was nullable, now default 0 NOT NULL
        // (mirrors reservations.discount_amount). Backfill NULL → 0 first.
        if (Schema::hasColumn('orders', 'discount_amount')) {
            DB::statement('UPDATE orders SET discount_amount = 0 WHERE discount_amount IS NULL');
            Schema::table('orders', function (Blueprint $table) {
                $table->decimal('discount_amount', 15, 2)->default(0)->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('discount_type', 20)->nullable()->after('subtotal');
            $table->decimal('discount_value', 15, 2)->nullable()->after('discount_type');
            $table->decimal('applied_penalty_rate', 5, 2)->nullable()->after('order_period');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['promo_code_applied']);
            $table->dropColumn(['penalty_amount', 'promo_code_applied']);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex(['promo_code_applied']);
            $table->dropColumn(['penalty_amount', 'promo_code_applied']);
        });
    }
};
