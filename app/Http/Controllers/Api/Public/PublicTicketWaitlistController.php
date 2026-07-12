<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicTicket\JoinWaitlistRequest;
use App\Http\Resources\PublicTicketOrderResource;
use App\Http\Resources\PublicTicketResource;
use App\Models\TicketWaitlistEntry;
use App\Services\Ticket\WaitlistService;
use Illuminate\Http\JsonResponse;

class PublicTicketWaitlistController extends Controller
{
    public function __construct(protected WaitlistService $waitlist) {}

    /**
     * Join the FIFO waitlist for a sold-out ticket.
     */
    public function join(JoinWaitlistRequest $request): JsonResponse
    {
        $entry = $this->waitlist->join($request->validated());

        return response()->json([
            'message' => 'You have been added to the waitlist. We will email you if a seat opens up.',
            'data' => [
                'status' => $entry->status->value,
                'position' => $entry->position,
                'quantity' => $entry->quantity,
            ],
        ], 201);
    }

    /**
     * Preview a claim offer (ticket, quantity, price, expiry) before the
     * claimant confirms, without consuming it.
     */
    public function showClaim(string $token): JsonResponse
    {
        $entry = TicketWaitlistEntry::query()
            ->where('claim_token', $token)
            ->with(['event', 'ticket.pricePhases'])
            ->first();

        abort_unless($entry, 404, 'This claim link is invalid.');

        return response()->json([
            'data' => [
                'status' => $entry->status->value,
                'quantity' => $entry->quantity,
                'offer_expires_at' => $entry->offer_expires_at,
                'is_active' => $entry->hasActiveOffer(),
                'event' => $entry->event?->only(['id', 'title', 'slug']),
                'ticket' => $entry->ticket ? new PublicTicketResource($entry->ticket) : null,
            ],
        ]);
    }

    /**
     * Execute a claim: create the order for the already-held quantity (the
     * seat was reserved when the offer was made - see
     * WaitlistService::offerReleasedSeats()).
     */
    public function claim(string $token): JsonResponse
    {
        $order = $this->waitlist->claim($token);

        return response()->json([
            'message' => $order->isFree() ? 'Tickets claimed successfully.' : 'Order created. Continue to payment.',
            'data' => new PublicTicketOrderResource($order->loadMissing(['items', 'attendees.ticket'])),
        ], 201);
    }
}
