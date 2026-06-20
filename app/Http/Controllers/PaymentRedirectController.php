<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\TicketOrder;
use App\Services\Reservation\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Bounces a payment-provider browser redirect back to the originating
 * event-website domain.
 *
 * Some redirect URLs are account-global and cannot be set per-transaction
 * (Midtrans dashboard finish/unfinish/error). Pointing those at this endpoint
 * lets PM One resolve the purchase from the appended `order_id` and 302 the
 * guest to the exact domain they bought on (stored as `return_origin`), instead
 * of a single global frontend. Provider-agnostic: it keys off `order_id`.
 *
 * The order_id prefix selects the product: `HTL-` => hotel reservation,
 * `TIX-` => ticket order. The same bouncer serves both.
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

        // Retry order_ids carry a `~N` suffix; the stored number is the bare value.
        $number = (string) preg_replace('/~\d+$/', '', $orderId);

        $failed = $this->isFailure($request);

        // Ticket orders are prefixed TIX-; everything else is a reservation.
        if (str_starts_with($number, 'TIX-')) {
            return $this->redirectForTicketOrder($number, $failed, $fallback);
        }

        return $this->redirectForReservation($number, $failed, $fallback);
    }

    private function redirectForReservation(string $number, bool $failed, string $fallback): RedirectResponse
    {
        $reservation = Reservation::query()->where('reservation_number', $number)->first();

        if (! $reservation) {
            return redirect()->away($fallback);
        }

        $origin = $reservation->return_origin ?: $fallback;

        if ($failed) {
            return redirect()->away($origin.'/hotels?failed='.urlencode($reservation->reservation_number));
        }

        $token = $this->reservations->magicLinkTokenFor($reservation);

        return redirect()->away(
            $origin.'/hotels/success?ref='.urlencode($reservation->reservation_number).'&token='.$token
        );
    }

    private function redirectForTicketOrder(string $number, bool $failed, string $fallback): RedirectResponse
    {
        $order = TicketOrder::query()->where('order_number', $number)->first();

        if (! $order) {
            return redirect()->away($fallback);
        }

        $origin = $order->return_origin ?: $fallback;

        if ($failed) {
            return redirect()->away($origin.'/tickets?failed='.urlencode($order->order_number));
        }

        $token = TicketOrder::magicLinkTokenFor($order->order_number);

        return redirect()->away(
            $origin.'/tickets/result?ref='.urlencode($order->order_number).'&token='.$token
        );
    }

    /**
     * Outcome signal: Midtrans appends `transaction_status`; Xendit + our own
     * links carry `result=success|failed`. Only an explicit failure goes to the
     * "failed" page; paid AND pending both land on the receipt/result page,
     * which shows the real (possibly still-pending) state via the magic link.
     */
    private function isFailure(Request $request): bool
    {
        $txStatus = strtolower((string) $request->query('transaction_status', ''));
        $result = strtolower((string) $request->query('result', ''));

        return in_array($txStatus, ['deny', 'cancel', 'expire', 'failure'], true) || $result === 'failed';
    }
}
