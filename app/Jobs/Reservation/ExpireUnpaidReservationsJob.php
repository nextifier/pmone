<?php

namespace App\Jobs\Reservation;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Services\Promotion\PromoCodeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExpireUnpaidReservationsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(PromoCodeService $promoCodes): int
    {
        $expiring = Reservation::query()
            ->where('status', ReservationStatus::PendingPayment->value)
            ->where('payment_expires_at', '<=', now())
            ->with('adjustments.promotionRule')
            ->get();

        if ($expiring->isEmpty()) {
            return 0;
        }

        foreach ($expiring as $reservation) {
            // Revert promo usage + void adjustments before flipping status.
            $promoCodes->voidAllOnCancel($reservation);

            $reservation->update([
                'status' => ReservationStatus::Expired->value,
            ]);

            activity()
                ->performedOn($reservation)
                ->event('auto_expired')
                ->withProperties([
                    'project_id' => $reservation->event?->project_id,
                    'reservation_id' => $reservation->id,
                ])
                ->log('Reservation auto-expired (unpaid)');
        }

        return $expiring->count();
    }
}
