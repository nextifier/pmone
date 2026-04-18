<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicReservation\StorePublicReservationRequest;
use App\Http\Resources\PublicReservationResource;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Services\Reservation\DocumentService;
use App\Services\Reservation\ReservationService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PublicReservationController extends Controller
{
    public function __construct(
        protected ReservationService $reservations,
        protected DocumentService $documents,
    ) {}

    public function store(StorePublicReservationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['ip_address'] = $request->ip();
        $data['user_agent'] = $request->userAgent();

        $hotel = Hotel::with('event')->findOrFail($data['hotel_id']);
        abort_if(! $hotel->event?->is_active, 422, 'This event is no longer accepting reservations.');

        $reservation = $this->reservations->createReservation($data);

        return response()->json([
            'data' => [
                'reservation_number' => $reservation->reservation_number,
                'magic_link_token' => $reservation->magicLinkRaw,
                'payment_url' => $reservation->payment_url,
                'status' => $reservation->status?->value,
                'total_amount' => (float) $reservation->total_amount,
            ],
            'message' => 'Reservation created successfully',
        ], 201);
    }

    public function showByMagicLink(string $token): JsonResponse
    {
        $reservation = $this->resolveByToken($token);

        return response()->json([
            'data' => (new PublicReservationResource($reservation))->resolve(),
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

        if (! $reservation->status->isPaid()) {
            abort(422, 'Receipt is only available after payment.');
        }

        return $this->documents->renderReceiptPdf($reservation);
    }

    private function resolveByToken(string $token): Reservation
    {
        $hashed = hash('sha256', $token);

        return Reservation::query()
            ->where('magic_link_token', $hashed)
            ->with(['hotel', 'event', 'items.roomType', 'transfers.transferOption'])
            ->firstOrFail();
    }
}
