<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicTicket\StorePublicTicketOrderRequest;
use App\Http\Resources\EventCustomFieldResource;
use App\Http\Resources\PublicTicketOrderResource;
use App\Models\CustomField;
use App\Models\TicketOrder;
use App\Services\Ticket\TicketDocumentService;
use App\Services\Ticket\TicketPurchaseService;
use App\Support\FormFieldTypes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class PublicTicketOrderController extends Controller
{
    public function __construct(protected TicketPurchaseService $purchases) {}

    public function store(StorePublicTicketOrderRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['ip_address'] = $request->ip();
        $data['user_agent'] = $request->userAgent();

        $order = $this->purchases->createOrder($data);

        $resource = (new PublicTicketOrderResource(
            $order->loadMissing(['items', 'attendees.ticket'])
        ));

        return response()->json([
            'data' => $resource,
            'message' => $order->isFree() ? 'Tickets claimed successfully.' : 'Order created. Continue to payment.',
        ], 201);
    }

    /**
     * Order status by its opaque ulid (used on the post-checkout result page).
     */
    public function show(string $ulid): JsonResponse
    {
        $order = TicketOrder::query()
            ->where('ulid', $ulid)
            ->with(['items', 'attendees.ticket'])
            ->firstOrFail();

        return response()->json([
            'data' => new PublicTicketOrderResource($order),
        ]);
    }

    /**
     * Order + all attendees via the emailed magic link (Manage Attendees for the
     * buyer without a login).
     */
    public function showByMagicLink(Request $request, string $token): JsonResponse
    {
        $order = TicketOrder::resolveByMagicLink($token);

        abort_unless($order, 404, 'This link is invalid or has expired.');

        $order->load(['items', 'attendees.ticket', 'attendees.customFieldValues.customField', 'event']);

        App::setLocale((string) $request->input('locale', config('app.locale', 'en')));

        // Registration questions + each attendee's answers so the manage page
        // renders per-attendee fields without a second fetch. Empty when the
        // event has none, so existing sites see no change.
        $registrationFields = $order->event
            ? $order->event->registrationFields()->where('is_active', true)->get()
            : collect();

        return response()->json([
            'data' => new PublicTicketOrderResource($order),
            'meta' => [
                'registration_fields' => EventCustomFieldResource::collection($registrationFields),
                'registration_answers' => $order->attendees->mapWithKeys(fn ($attendee) => [
                    $attendee->ulid => $attendee->customFieldValues
                        ->filter(fn ($value) => $value->customField?->context === CustomField::CONTEXT_TICKET_REGISTRATION)
                        ->mapWithKeys(fn ($value) => [
                            $value->customField->ulid => FormFieldTypes::normalizeStored($value->customField->type, $value->value),
                        ]),
                ]),
            ],
        ]);
    }

    /**
     * Order invoice (the bill) as an on-the-fly PDF, reachable via the magic link.
     */
    public function invoicePdfByMagicLink(string $token, TicketDocumentService $documents): Response
    {
        $order = TicketOrder::resolveByMagicLink($token);

        abort_unless($order, 404, 'This link is invalid or has expired.');

        return $documents->renderInvoicePdf($order);
    }

    /**
     * Payment receipt (proof of payment) as an on-the-fly PDF. Available only once
     * the order has been paid.
     */
    public function receiptPdfByMagicLink(string $token, TicketDocumentService $documents): Response
    {
        $order = TicketOrder::resolveByMagicLink($token);

        abort_unless($order, 404, 'This link is invalid or has expired.');
        abort_if($order->paid_at === null, 422, 'Receipt is only available after payment.');

        return $documents->renderReceiptPdf($order);
    }
}
