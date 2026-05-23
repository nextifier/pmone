<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Promo\PreviewPricingRequest;
use App\Http\Requests\PublicReservation\StorePublicReservationRequest;
use App\Http\Resources\PublicReservationResource;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\HotelEvent;
use App\Models\Reservation;
use App\Services\Pricing\PricingService;
use App\Services\Promotion\PenaltyService;
use App\Services\Promotion\PromoCodeService;
use App\Services\Reservation\DocumentService;
use App\Services\Reservation\ReservationService;
use App\Services\Reservation\TransientReservationBuilder;
use App\Services\Xendit\XenditErrorMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PublicReservationController extends Controller
{
    public function __construct(
        protected ReservationService $reservations,
        protected DocumentService $documents,
        protected PricingService $pricing,
        protected TransientReservationBuilder $transientReservation,
        protected PromoCodeService $promoCodes,
        protected PenaltyService $penalties,
    ) {}

    public function previewPricing(PreviewPricingRequest $request): JsonResponse
    {
        $data = $request->validated();

        $reservation = $this->transientReservation->build([
            'hotel_id' => $data['hotel_id'],
            'event_id' => $data['event_id'] ?? null,
            'guest_email' => $data['guest_email'] ?? '',
            'items' => $data['items'] ?? [],
            'transfers' => $data['transfers'] ?? [],
        ]);

        $hypotheticalRule = null;
        $promoValidation = null;

        if (! empty($data['promo_code']) && ! empty($data['guest_email'])) {
            $promoValidation = $this->promoCodes->validate(
                $data['promo_code'],
                $reservation,
                $data['guest_email'],
                null,
            );

            if ($promoValidation->valid) {
                $hypotheticalRule = $promoValidation->rule;
            }
        }

        $result = $this->pricing->preview($reservation, $hypotheticalRule);

        return response()->json([
            'data' => [
                'pricing' => $result->toArray(),
                'promo_validation' => $promoValidation?->toArray(),
            ],
        ]);
    }

    public function store(StorePublicReservationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['ip_address'] = $request->ip();
        $data['user_agent'] = $request->userAgent();
        // COMPONENTS-mode sessions need allowed iframe origins. Derive from
        // the request's Origin header; ignored for hosted/legacy flows.
        $data['origins'] = $this->originsFromRequest($request);

        $hotel = Hotel::query()->findOrFail($data['hotel_id']);
        $event = Event::query()->findOrFail($data['event_id']);

        abort_if(! $hotel->is_active, 422, 'This hotel is no longer accepting reservations.');
        abort_if(! $event->is_active, 422, 'This event is no longer accepting reservations.');

        $pivot = HotelEvent::query()
            ->where(['hotel_id' => $hotel->id, 'event_id' => $event->id, 'is_active' => true])
            ->first();
        abort_if(! $pivot, 422, 'This hotel is not active for the requested event.');

        $reservation = $this->reservations->createReservation($data);

        return response()->json([
            'data' => [
                'reservation_number' => $reservation->reservation_number,
                'magic_link_token' => $reservation->magicLinkRaw,
                'payment_url' => $reservation->payment_url,
                // Short-lived Xendit Components SDK key; non-null only for a
                // COMPONENTS-mode gateway. Frontend uses it to mount the
                // embedded checkout directly without an extra round-trip.
                'components_sdk_key' => $reservation->components_sdk_key,
                'status' => $reservation->status?->value,
                'total_amount' => (float) $reservation->total_amount,
            ],
            'message' => 'Reservation created successfully',
        ], 201);
    }

    public function showByMagicLink(string $token, Request $request): JsonResponse
    {
        $reservation = $this->resolveByToken($token);

        // A pending COMPONENTS-mode reservation needs a fresh SDK key on each
        // page load (the previous one is short-lived). The service no-ops for
        // any other state, so the call is safe to make unconditionally.
        $reservation = $this->reservations->refreshComponentsSession(
            $reservation,
            $this->originsFromRequest($request),
        );

        return response()->json([
            'data' => (new PublicReservationResource($reservation))->resolve(),
        ]);
    }

    /**
     * Lightweight status lookup by reservation number. Returns only the
     * non-sensitive fields the success page needs to render its step badges
     * when the visitor lands without a magic-link token (e.g. Xendit
     * redirected with `?ref=` only). Reservation numbers are non-secret —
     * they appear on invoices, emails, and admin URLs — so exposing status
     * is safe. Full details (guest, items, totals) still require the magic
     * token.
     */
    public function statusByNumber(string $reservationNumber): JsonResponse
    {
        $reservation = Reservation::query()
            ->where('reservation_number', $reservationNumber)
            ->first();

        abort_if(! $reservation, 404, 'Reservation not found.');

        return response()->json([
            'data' => [
                'reservation_number' => $reservation->reservation_number,
                'status' => $reservation->status?->value,
                'status_label' => $reservation->status?->label(),
            ],
        ]);
    }

    public function retryPaymentByMagicLink(string $token, Request $request): JsonResponse
    {
        $reservation = $this->resolveByToken($token);

        try {
            $reservation = $this->reservations->retryXenditInvoice(
                $reservation,
                null,
                $this->originsFromRequest($request),
            );
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $mapped = XenditErrorMapper::map($e);

            Log::log($mapped['log_level'], 'Retry payment failed', [
                'reservation_id' => $reservation->id,
                'error_code' => $mapped['error_code'],
                'raw_error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $mapped['message'],
                'error_code' => $mapped['error_code'],
            ], $mapped['http_status']);
        }

        return response()->json([
            'data' => (new PublicReservationResource($reservation))->resolve(),
            'message' => 'Payment link regenerated.',
        ]);
    }

    public function invoicePdfByMagicLink(string $token): Response
    {
        $reservation = $this->resolveByToken($token);

        return $this->documents->renderInvoicePdf($reservation);
    }

    public function receiptPdfByMagicLink(string $token): Response
    {
        $reservation = $this->resolveByToken($token);

        // Receipt stays available after cancellation/refund - it is proof of the
        // original payment, so gate on whether payment ever happened.
        if ($reservation->paid_at === null) {
            abort(422, 'Receipt is only available after payment.');
        }

        return $this->documents->renderReceiptPdf($reservation);
    }

    public function voucherByMagicLink(string $token): Response
    {
        $reservation = $this->resolveByToken($token);

        $media = $reservation->getFirstMedia('voucher');

        if (! $media) {
            abort(404, 'The check-in voucher is not available yet.');
        }

        return Storage::disk($media->disk)->download(
            $media->getPathRelativeToRoot(),
            $media->file_name,
        );
    }

    /**
     * Collect candidate origins that the Xendit Components SDK may load from.
     * The Origin header is preferred (it identifies the actual calling
     * website); the configured frontend URL is appended as a fallback so
     * server-to-server callers without an Origin still get a usable list.
     *
     * Xendit validates these and rejects unknown values, so anything malformed
     * is dropped — XenditService::normaliseOrigins handles final filtering.
     *
     * @return array<int, string>
     */
    private function originsFromRequest(Request $request): array
    {
        $origins = [];

        $origin = (string) $request->header('Origin');
        if ($origin !== '') {
            $origins[] = $origin;
        }

        $frontend = (string) config('app.frontend_url');
        if ($frontend !== '') {
            $origins[] = $frontend;
        }

        return $origins;
    }

    /**
     * Resolve a reservation by raw magic-link token.
     *
     * H3: Per-token rate limit prevents enumeration via distributed IPs. After
     * 10 invalid lookups against the same token hash, requests are blocked for
     * 10 minutes — independent of the per-route IP throttle.
     */
    private function resolveByToken(string $token): Reservation
    {
        $hashed = hash('sha256', $token);
        $key = "magic_link_lookup:{$hashed}";

        if (RateLimiter::tooManyAttempts($key, 10)) {
            abort(429, 'Too many attempts. Try again later.');
        }

        $reservation = Reservation::query()
            ->where('magic_link_token', $hashed)
            ->with(['hotel', 'event', 'items.roomType', 'transfers.transferOption'])
            ->first();

        if (! $reservation) {
            RateLimiter::hit($key, 600); // 10 min decay
            abort(404);
        }

        if ($reservation->magic_link_expires_at && $reservation->magic_link_expires_at->isPast()) {
            abort(410, 'This magic link has expired.');
        }

        return $reservation;
    }
}
