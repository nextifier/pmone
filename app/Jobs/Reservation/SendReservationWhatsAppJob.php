<?php

namespace App\Jobs\Reservation;

use App\Helpers\PhoneCountryHelper;
use App\Models\Reservation;
use App\Services\Reservation\ReservationService;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends the paid-booking confirmation to the guest over WhatsApp.
 *
 * Dispatched from ReservationService::markAsPaid() alongside SendBookingReceivedJob,
 * guarded by the same atomic pending -> paid transition so it fires exactly once.
 */
class SendReservationWhatsAppJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    public function __construct(public int $reservationId) {}

    public function handle(WhatsAppService $whatsapp, ReservationService $reservations): void
    {
        $reservation = Reservation::query()
            ->with(['event', 'hotel'])
            ->find($this->reservationId);

        if (! $reservation) {
            return;
        }

        $to = PhoneCountryHelper::toWhatsAppNumber((string) $reservation->guest_phone);

        if ($to === '') {
            Log::warning('SendReservationWhatsAppJob skipped: empty guest phone', [
                'reservation_id' => $reservation->id,
            ]);

            return;
        }

        // Reuse the reservation's stable magic-link token so the WhatsApp link
        // matches the one embedded in the payment success_url and the email.
        $rawToken = $reservations->magicLinkTokenFor($reservation);
        $reservationUrl = rtrim((string) config('app.frontend_url'), '/')."/hotels/reservation/{$rawToken}";

        // NOTE: template name, language code, and parameter order MUST match the
        // UTILITY template approved in Meta Business Manager. Adjust here if the
        // approved template differs.
        $whatsapp->sendTemplate(
            $to,
            'ticket_confirmation',
            [
                $reservation->guest_name,
                $reservation->event?->title,
                $reservation->reservation_number,
                $reservationUrl,
            ],
            'id',
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::critical('SendReservationWhatsAppJob failed permanently', [
            'reservation_id' => $this->reservationId,
            'error' => $exception->getMessage(),
        ]);
    }
}
