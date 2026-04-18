<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\Reservation\ReservationService;
use App\Services\Xendit\XenditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class XenditWebhookController extends Controller
{
    public function __construct(
        protected XenditService $xendit,
        protected ReservationService $reservations,
    ) {}

    public function invoice(Request $request): JsonResponse
    {
        if (! $this->xendit->verifyWebhookToken($request)) {
            Log::warning('Xendit webhook signature mismatch', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $payload = $request->all();
        $externalId = $payload['external_id'] ?? null;
        $invoiceId = $payload['id'] ?? null;
        $status = strtolower($payload['status'] ?? '');

        if (! $externalId) {
            return response()->json(['message' => 'Missing external_id'], 422);
        }

        $reservation = Reservation::query()
            ->where('reservation_number', $externalId)
            ->first();

        if (! $reservation) {
            Log::warning('Xendit webhook: reservation not found', ['external_id' => $externalId]);

            return response()->json(['message' => 'Reservation not found'], 404);
        }

        if ($status === 'paid' || $status === 'settled') {
            $this->reservations->markAsPaid($reservation, $invoiceId);

            return response()->json(['message' => 'Reservation marked as paid']);
        }

        if ($status === 'expired') {
            $this->reservations->expireReservation($reservation);

            return response()->json(['message' => 'Reservation expired']);
        }

        return response()->json(['message' => 'Webhook received but no action taken']);
    }
}
