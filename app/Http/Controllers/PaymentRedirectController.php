<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Services\Reservation\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Bounces a payment-provider browser redirect back to the originating
 * event-website domain.
 *
 * Some redirect URLs are account-global and cannot be set per-transaction
 * (Midtrans dashboard finish/unfinish/error). Pointing those at this endpoint
 * lets PM One resolve the reservation from the appended `order_id` and 302 the
 * guest to the exact domain they booked on (stored as `return_origin`), instead
 * of a single global frontend. Provider-agnostic: it keys off `order_id`.
 */
class PaymentRedirectController extends Controller
{
    public function __construct(protected ReservationService $reservations) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $fallback = rtrim((string) config('app.frontend_url'), '/');

        $orderId = (string) $request->query('order_id', '');
        if ($orderId === '') {
            return redirect()->away($fallback);
        }

        // Retry order_ids carry a `~N` suffix; the reservation keeps the bare number.
        $reservationNumber = (string) preg_replace('/~\d+$/', '', $orderId);

        $reservation = Reservation::query()
            ->where('reservation_number', $reservationNumber)
            ->first();

        if (! $reservation) {
            return redirect()->away($fallback);
        }

        $origin = $reservation->return_origin ?: $fallback;

        // Outcome signal: Midtrans appends `transaction_status`; Xendit + our own
        // links carry `result=success|failed`. Only an explicit failure goes to
        // the "failed" page — paid AND pending both land on the receipt page,
        // which shows the real (possibly still-pending) state via the magic link.
        $txStatus = strtolower((string) $request->query('transaction_status', ''));
        $result = strtolower((string) $request->query('result', ''));

        $failed = in_array($txStatus, ['deny', 'cancel', 'expire', 'failure'], true)
            || $result === 'failed';

        if ($failed) {
            return redirect()->away(
                $origin.$this->productPath($reservation).'?failed='.urlencode($reservation->reservation_number)
            );
        }

        $token = $this->reservations->magicLinkTokenFor($reservation);

        return redirect()->away(
            $origin.$this->productPath($reservation).'/success?ref='.urlencode($reservation->reservation_number).'&token='.$token
        );
    }

    /**
     * Product base path for the redirect, derived from the order_id prefix so the
     * same bouncer can serve future products (tickets, etc.). Hotels today.
     */
    private function productPath(Reservation $reservation): string
    {
        return '/hotels';
    }
}
