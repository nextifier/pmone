<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicTicket\ValidateAccessCodeRequest;
use App\Http\Resources\PublicTicketResource;
use App\Models\Event;
use App\Services\Ticket\AccessCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;

class PublicAccessCodeController extends Controller
{
    public function __construct(protected AccessCodeService $accessCodes) {}

    /**
     * Validate an access code for an event (the ONLY way a `hidden` ticket is
     * revealed). Uncached + rate-limited. Returns a minimal public-safe shape
     * (unlocked tickets + price-effect preview) and never leaks code internals.
     */
    public function validate(ValidateAccessCodeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $event = Event::query()->findOrFail($data['event_id']);
        App::setLocale((string) $request->input('locale', config('app.locale', 'en')));

        $validation = $this->accessCodes->validate(
            (string) $data['code'],
            $event,
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['items'] ?? [],
            hasPromo: false,
        );

        $payload = $validation->toArray();

        // On success, return the FULL purchasable shape of the unlocked tickets
        // (including hidden ones) so the event website can render them. This stays
        // uncached + throttled + gated behind a valid code, so it never leaks.
        if ($validation->valid) {
            $ticketIds = collect($validation->unlocks)->pluck('ticket_id')->all();
            $tickets = $event->tickets()
                ->whereIn('id', $ticketIds)
                ->where('is_active', true)
                ->with(['media', 'pricePhases', 'sessions', 'validDays'])
                ->orderBy('order_column')
                ->get();

            $payload['tickets'] = PublicTicketResource::collection($tickets)->resolve();
        }

        return response()->json([
            'data' => $payload,
        ], $validation->valid ? 200 : 422);
    }
}
