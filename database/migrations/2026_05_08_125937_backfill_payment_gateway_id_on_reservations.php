<?php

use App\Models\Project;
use App\Models\Reservation;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Backfill payment_gateway_id on existing Xendit reservations using each
     * project's current default Xendit gateway (latest active gateway for the
     * mode the reservation was paid with). Reservations whose project has no
     * matching gateway are left null and fall back to the runtime resolver.
     *
     * No-op on fresh installs (no reservations with xendit_invoice_id yet).
     */
    public function up(): void
    {
        $mode = app()->environment('production') ? 'live' : 'test';

        Reservation::query()
            ->whereNotNull('xendit_invoice_id')
            ->whereNull('payment_gateway_id')
            ->with('event.project')
            ->chunkById(200, function ($reservations) use ($mode) {
                foreach ($reservations as $reservation) {
                    $project = $reservation->event?->project;
                    if (! $project instanceof Project) {
                        continue;
                    }

                    $gateway = $project->defaultPaymentGateway('xendit', $mode);
                    if (! $gateway) {
                        continue;
                    }

                    $reservation->forceFill(['payment_gateway_id' => $gateway->id])->saveQuietly();
                }
            });
    }

    public function down(): void
    {
        // Backfill is one-way; reverting would lose audit data.
        // To rollback, manually set payment_gateway_id = NULL where appropriate.
    }
};
