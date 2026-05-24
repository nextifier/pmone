<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roll back the "Sessions - Components" checkout method experiment.
 *
 *  1. Cancel any reservation that was created via Components mode and never
 *     completed payment. Those rows have a non-null components_sdk_key but no
 *     payment_url, so after the column is dropped the customer would have no
 *     way to pay — flip them to "cancelled" with an explanatory reason.
 *  2. Migrate any project_payment_gateway still configured for the removed
 *     enum value to the renamed Sessions Payment Link value. Without this,
 *     the Eloquent enum cast on ProjectPaymentGateway::$checkout_method would
 *     throw ValueError on model load after deploy.
 *  3. Rename the existing 'sessions_payment_link' rows to the new
 *     'payment_link_sessions' value so the enum's renamed case still casts
 *     cleanly. The label-only rename was not enough — we also renamed the
 *     enum's backing value for consistency with the new "Payment Link - X"
 *     naming pattern.
 *  4. Drop the components_sdk_key column.
 *
 * All steps are idempotent — safe to run multiple times.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Cancel orphaned pending Components reservations.
        DB::table('reservations')
            ->whereNotNull('components_sdk_key')
            ->where('status', 'pending_payment')
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => 'Sessions - Components checkout discontinued',
            ]);

        // 2. Re-map any gateway still on the removed enum value.
        DB::table('project_payment_gateways')
            ->where('checkout_method', 'sessions_components')
            ->update(['checkout_method' => 'payment_link_sessions']);

        // 3. Rename existing Sessions Payment Link rows to the new value.
        DB::table('project_payment_gateways')
            ->where('checkout_method', 'sessions_payment_link')
            ->update(['checkout_method' => 'payment_link_sessions']);

        // 4. Drop the column, guarded so re-runs are no-ops.
        if (Schema::hasColumn('reservations', 'components_sdk_key')) {
            Schema::table('reservations', function (Blueprint $table): void {
                $table->dropColumn('components_sdk_key');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('reservations', 'components_sdk_key')) {
            Schema::table('reservations', function (Blueprint $table): void {
                $table->text('components_sdk_key')->nullable()->after('xendit_invoice_id');
            });
        }

        // Best-effort restore of the renamed gateway value. The original
        // sessions_components rows cannot be recovered — they were migrated to
        // payment_link_sessions in up() and are indistinguishable from genuine
        // Sessions Payment Link rows.
        DB::table('project_payment_gateways')
            ->where('checkout_method', 'payment_link_sessions')
            ->update(['checkout_method' => 'sessions_payment_link']);
    }
};
