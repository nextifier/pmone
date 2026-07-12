<?php

namespace App\Services\Ticket;

use App\Contracts\Payment\CreatesCheckout;
use App\Enums\Ticketing\PurchaseType;
use App\Enums\Ticketing\TicketOrderStatus;
use App\Jobs\Ticket\CreateTicketCheckoutJob;
use App\Jobs\Ticket\GenerateBulkAttendeesJob;
use App\Jobs\Ticket\SendAttendeeETicketJob;
use App\Jobs\Ticket\SendTicketOrderConfirmationJob;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketOrderItem;
use App\Models\TicketPricePhase;
use App\Models\TicketSession;
use App\Models\User;
use App\Services\Payment\PaymentProviderFactory;
use App\Services\Pricing\PricingService;
use App\Services\Promotion\PromoCodeService;
use App\Services\Xendit\XenditErrorMapper;
use App\Support\CustomFieldValues;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Creates ticket orders end-to-end: resolve the active price phase per ticket,
 * compute the total, hold inventory, issue N attendees, register the buyer
 * lazily, then either confirm a free order or open a payment with the project's
 * gateway. Mirrors ReservationService but for the ticketing domain.
 */
class TicketPurchaseService
{
    public function __construct(
        protected ?PricingService $pricing = null,
        protected ?PromoCodeService $promoCodes = null,
        protected ?AccessCodeService $accessCodes = null,
    ) {
        $this->pricing ??= app(PricingService::class);
        $this->promoCodes ??= app(PromoCodeService::class);
        $this->accessCodes ??= app(AccessCodeService::class);
    }

    /**
     * The price phase whose window contains "now" for a ticket, or null when the
     * ticket is not currently on sale.
     */
    public function resolveActivePhase(Ticket $ticket, ?Carbon $now = null): ?TicketPricePhase
    {
        $now ??= now();

        return $ticket->pricePhases
            ->where('is_active', true)
            ->first(fn (TicketPricePhase $phase) => $phase->isActiveAt($now));
    }

    /**
     * The price phase to charge for a purchase of $qty: walks active phases
     * whose window contains "now" IN PHASE ORDER and returns the first with
     * quota capacity for $qty. A time-active phase that is sold out (or that
     * cannot fit the full requested quantity) is skipped in favor of the next
     * active phase that also covers "now"; null when none has capacity, so the
     * line is rejected as not currently on sale rather than oversold at a
     * stale price.
     */
    public function resolveActivePhaseForPurchase(Ticket $ticket, int $qty, ?Carbon $now = null): ?TicketPricePhase
    {
        $now ??= now();

        return $ticket->pricePhases
            ->where('is_active', true)
            ->filter(fn (TicketPricePhase $phase) => $phase->isActiveAt($now))
            ->first(fn (TicketPricePhase $phase) => $phase->hasCapacityFor($qty));
    }

    /**
     * Quantity still sellable for a ticket: stock minus `sold_count`, which
     * is the AUTHORITATIVE running counter maintained by the atomic
     * reserve()/release() calls in createOrder()/expireOrder()/
     * refundAttendee()/reconfirmAfterExpiry() - not a live SUM over order
     * rows. Null stock = unlimited. Admin comp batches (bulkGenerate) never
     * touch `sold_count`, so they stay outside sale stock exactly as before.
     */
    public function availableStock(Ticket $ticket): ?int
    {
        if ($ticket->stock === null) {
            return null;
        }

        return max(0, $ticket->stock - $ticket->sold_count);
    }

    /**
     * Remaining capacity of an add-on session, or null when uncapped. Reads
     * the authoritative `booked_count` counter - see availableStock().
     */
    public function availableSessionCapacity(TicketSession $session): ?int
    {
        if ($session->capacity === null) {
            return null;
        }

        return max(0, $session->capacity - $session->booked_count);
    }

    /**
     * Compute a cart preview without persisting anything. When a promo code is
     * given, it is validated + priced against a transient order so the checkout
     * can show the discount + final total before the buyer commits.
     *
     * @param  array<int, array{ticket_id:int, quantity?:int, ticket_session_id?:int|null}>  $items
     * @return array{lines: array<int, array<string, mixed>>, subtotal: float, on_sale: bool, discount: float, total: float, promo: array<string, mixed>|null, access: array<string, mixed>|null}
     */
    public function previewCart(Event $event, array $items, ?string $promoCode = null, ?string $email = null, ?string $accessCode = null, ?string $phone = null): array
    {
        $now = now();
        $lines = [];
        $subtotal = 0.0;
        $onSale = true;

        // Resolve the access code's basic eligibility (revoked/expired/bind
        // checks only, no per-ticket gating) up front so gated ticket lines
        // below can check unlocksTicket() while the cart is being built.
        // Unlike createOrder, an ineligible or absent code must NOT abort the
        // whole preview - it should just leave gated tickets unpriced.
        $accessCodeModel = null;
        if (! empty($accessCode)) {
            $eligibility = $this->accessCodes->validate(
                (string) $accessCode,
                $event,
                $email,
                $phone,
                cartItems: [],
                hasPromo: ! empty($promoCode),
            );

            if ($eligibility->valid) {
                $accessCodeModel = $eligibility->code;
            }
        }

        foreach ($items as $item) {
            $ticket = $event->tickets()
                ->with('pricePhases')
                ->where('id', $item['ticket_id'])
                ->first();

            if (! $ticket || ! $ticket->is_active || $ticket->purchase_type !== PurchaseType::FirstParty) {
                continue;
            }

            // Hidden/code_required tickets never price without a valid access
            // code that unlocks them - mirrors createOrder's gating so a
            // crafted preview request cannot leak a hidden ticket's
            // title/phase/price.
            if ($ticket->isGated() && (! $accessCodeModel || ! $accessCodeModel->unlocksTicket($ticket->id))) {
                continue;
            }

            $qty = max(1, (int) ($item['quantity'] ?? 1));
            $phase = $this->resolveActivePhase($ticket, $now);

            if (! $phase) {
                $onSale = false;

                continue;
            }

            $unit = (float) $phase->price;
            $lineSubtotal = $unit * $qty;
            $subtotal += $lineSubtotal;

            $lines[] = [
                'ticket_id' => $ticket->id,
                'title' => $ticket->getTranslation('title', app()->getLocale(), false),
                'quantity' => $qty,
                'unit_price' => $unit,
                'phase_label' => $phase->label,
                'ticket_session_id' => $item['ticket_session_id'] ?? null,
                'subtotal' => $lineSubtotal,
            ];
        }

        $accessDiscount = 0.0;
        $access = null;

        // Access code first (gate + optional price effect), scoped to unlocked lines.
        if (! empty($accessCode)) {
            $cartItems = collect($lines)->map(fn (array $l) => [
                'ticket_id' => $l['ticket_id'],
                'quantity' => $l['quantity'],
            ])->all();

            $acValidation = $this->accessCodes->validate(
                (string) $accessCode,
                $event,
                $email,
                $phone,
                $cartItems,
                hasPromo: ! empty($promoCode),
            );

            if ($acValidation->valid) {
                if ($acValidation->priceEffect && $acValidation->priceEffect->affectsPrice()) {
                    $accessDiscount = min((float) ($acValidation->previewDiscount ?? 0.0), $subtotal);
                }

                $access = [
                    'code' => strtoupper(trim((string) $accessCode)),
                    'unlocks' => $acValidation->unlocks,
                    'price_effect' => $acValidation->priceEffect?->value,
                    'discount' => $accessDiscount,
                ];
            } else {
                $access = ['error_code' => $acValidation->errorCode, 'message' => $acValidation->message];
            }
        }

        $promoDiscount = 0.0;
        $promo = null;
        $remainingBase = max(0.0, $subtotal - $accessDiscount);

        if (! empty($promoCode) && $onSale && $remainingBase > 0) {
            $transient = $this->transientOrderForPreview($event, $lines, $remainingBase, $email);
            $validation = $this->promoCodes->validate((string) $promoCode, $transient, (string) ($email ?? ''));

            if ($validation->errorCode === null) {
                $promoDiscount = min((float) ($validation->previewDiscount ?? 0.0), $remainingBase);
                $promo = ['code' => strtoupper(trim((string) $promoCode)), 'discount' => $promoDiscount];
            } else {
                $promo = ['error_code' => $validation->errorCode, 'message' => $validation->message];
            }
        }

        $discount = $accessDiscount + $promoDiscount;

        return [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'on_sale' => $onSale,
            'discount' => $discount,
            'total' => max(0.0, $subtotal - $discount),
            'promo' => $promo,
            'access' => $access,
        ];
    }

    /**
     * Build an unsaved TicketOrder (with in-memory items) so the promo engine can
     * price a cart without persisting anything.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    protected function transientOrderForPreview(Event $event, array $lines, float $subtotal, ?string $email): TicketOrder
    {
        $order = new TicketOrder([
            'event_id' => $event->id,
            'buyer_email' => $email,
            'subtotal' => $subtotal,
        ]);

        $items = collect($lines)->map(fn (array $line) => new TicketOrderItem([
            'ticket_id' => $line['ticket_id'],
            'quantity' => $line['quantity'],
            'unit_price' => $line['unit_price'],
            'subtotal' => $line['subtotal'],
        ]));

        $order->setRelation('items', $items);

        return $order;
    }

    /**
     * Create a ticket order (items + attendees) and resolve free vs paid.
     *
     * @param  array<string, mixed>  $data
     */
    public function createOrder(array $data, ?CreatesCheckout $checkoutClient = null): TicketOrder
    {
        $event = Event::with('project')->findOrFail($data['event_id']);
        abort_unless((bool) $event->tickets_enabled, 422, 'Tickets are not available for this event.');

        $items = $data['items'] ?? [];
        abort_if(empty($items), 422, 'At least one ticket is required.');

        $idempotencyKey = $this->normalizeIdempotencyKey($data['idempotency_key'] ?? null);

        $result = DB::transaction(function () use ($data, $event, $items, $idempotencyKey) {
            // A client retrying a submission (double-click, network timeout)
            // sends the same key: hand back the order already created for it
            // instead of holding inventory a second time.
            if ($idempotencyKey !== null) {
                $existing = TicketOrder::query()
                    ->where('event_id', $event->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing) {
                    return ['order' => $existing->fresh(['items.attendees', 'event']), 'duplicate' => true];
                }
            }

            $buyerUser = $this->resolveBuyerUser($data);

            if ($buyerUser && ! empty($data['business_matching'])) {
                $this->storeBusinessMatching($buyerUser, $event, (array) $data['business_matching']);
            }

            // Resolve + validate the access code once (eligibility, bind, stacking)
            // before locking inventory. The authoritative re-validation + atomic
            // hold happens inside AccessCodeService::apply().
            $accessCodeModel = null;
            if (! empty($data['access_code'])) {
                $cartItems = collect($items)->map(fn ($i) => [
                    'ticket_id' => (int) $i['ticket_id'],
                    'quantity' => max(1, (int) ($i['quantity'] ?? 1)),
                ])->all();

                $validation = $this->accessCodes->validate(
                    (string) $data['access_code'],
                    $event,
                    $data['buyer_email'] ?? null,
                    $data['buyer_phone'] ?? null,
                    $cartItems,
                    hasPromo: ! empty($data['promo_code']),
                );

                abort_if(! $validation->valid, 422, $validation->message ?? 'Invalid access code.');
                $accessCodeModel = $validation->code;
            }

            $resolved = [];
            $subtotal = 0.0;

            // Availability is a PER-TICKET (and per-session) quantity, not a
            // per-line one. Aggregate every line's quantity by ticket/session id
            // BEFORE validating so a payload with two lines for the same ticket
            // cannot each pass an independent "available >= qty" check and
            // jointly oversell.
            $requestedByTicket = [];
            $requestedBySession = [];
            foreach ($items as $item) {
                $ticketId = (int) ($item['ticket_id'] ?? 0);
                $qty = max(1, (int) ($item['quantity'] ?? 1));
                $requestedByTicket[$ticketId] = ($requestedByTicket[$ticketId] ?? 0) + $qty;

                if (! empty($item['ticket_session_id'])) {
                    $sessionId = (int) $item['ticket_session_id'];
                    $requestedBySession[$sessionId] = ($requestedBySession[$sessionId] ?? 0) + $qty;
                }
            }

            // Total attendee headcount requested across every line, regardless
            // of ticket type - the event-level cap (fire-code / venue limit) is
            // independent of ticket mix, so it is checked against this single
            // aggregate rather than per-ticket.
            $totalRequestedQty = array_sum($requestedByTicket);

            $stockReserved = [];
            $phaseReserved = [];
            $sessionReserved = [];
            $eventHeadcountReserved = false;

            // Every reservation made for an earlier line/ticket in this loop
            // is tracked here so a later line's rejection can release them
            // before aborting - otherwise a request that partially succeeds
            // and then hits a validation failure on a later line would leave
            // the earlier lines' stock/phase/session counters permanently
            // held. A genuine insert failure AFTER this loop does not need
            // this: the surrounding DB::transaction rollback reverts the
            // reserve UPDATEs too, so manual release is only needed on this
            // abort-before-commit path.
            $reservations = [];
            $fail = function (string $message) use (&$reservations, &$eventHeadcountReserved, $event, $totalRequestedQty): void {
                $this->releaseReservations($reservations);
                if ($eventHeadcountReserved) {
                    $event->releaseHeadcount($totalRequestedQty);
                }
                abort(422, $message);
            };

            // Reserve the event-level total-headcount cap FIRST, before any
            // per-ticket/phase/session reserve, so a capacity-full event is
            // rejected without ever touching another counter. Null capacity
            // (the default) is uncapped and always succeeds.
            if (! $event->reserveHeadcount($totalRequestedQty)) {
                $fail('Event is at capacity.');
            }
            $eventHeadcountReserved = true;

            foreach ($items as $idx => $item) {
                // No lockForUpdate(): the atomic conditional UPDATEs in
                // reserve() below (not a row lock) are the serialization
                // point for concurrent buyers of the same ticket, so this
                // read can run unlocked and in parallel with other buyers.
                $ticket = Ticket::query()
                    ->where('id', $item['ticket_id'])
                    ->where('event_id', $event->id)
                    ->where('is_active', true)
                    ->where('purchase_type', PurchaseType::FirstParty->value)
                    ->with(['pricePhases', 'validDays'])
                    ->first();

                if (! $ticket) {
                    $fail("Ticket #{$idx} is invalid or not on sale.");
                }

                // Gated tickets (hidden/code_required) require a valid access code
                // that unlocks them. Public tickets are unrestricted.
                if ($ticket->isGated() && (! $accessCodeModel || ! $accessCodeModel->unlocksTicket($ticket->id))) {
                    $fail("Ticket {$ticket->slug} requires a valid access code.");
                }

                $qty = max(1, (int) ($item['quantity'] ?? 1));
                if ($ticket->min_quantity && $qty < $ticket->min_quantity) {
                    $fail("Minimum quantity for {$ticket->slug} is {$ticket->min_quantity}.");
                }
                if ($ticket->max_quantity && $requestedByTicket[$ticket->id] > $ticket->max_quantity) {
                    $fail("Maximum quantity for {$ticket->slug} is {$ticket->max_quantity}.");
                }

                $phase = $this->resolveActivePhaseForPurchase($ticket, $requestedByTicket[$ticket->id]);
                if (! $phase) {
                    $fail("Ticket {$ticket->slug} is not currently on sale.");
                }

                // Reserved once per ticket/phase id (not per line) - the
                // aggregated requested quantity is the same on every line for
                // this ticket, so reserving per line would double-count it.
                if (! isset($stockReserved[$ticket->id])) {
                    if (! $ticket->reserve($requestedByTicket[$ticket->id])) {
                        $fail("Only {$this->availableStock($ticket->fresh())} left for {$ticket->slug}.");
                    }
                    $stockReserved[$ticket->id] = true;
                    $reservations[] = ['model' => $ticket, 'qty' => $requestedByTicket[$ticket->id]];
                }

                if (! isset($phaseReserved[$phase->id])) {
                    if (! $phase->reserve($requestedByTicket[$ticket->id])) {
                        $fail("Ticket {$ticket->slug} is not currently on sale.");
                    }
                    $phaseReserved[$phase->id] = true;
                    $reservations[] = ['model' => $phase, 'qty' => $requestedByTicket[$ticket->id]];
                }

                $session = null;
                if (! empty($item['ticket_session_id'])) {
                    $session = TicketSession::query()
                        ->where('id', $item['ticket_session_id'])
                        ->where('ticket_id', $ticket->id)
                        ->where('is_active', true)
                        ->first();
                    if (! $session) {
                        $fail('Selected session is invalid for this ticket.');
                    }

                    if (! isset($sessionReserved[$session->id])) {
                        if (! $session->reserve($requestedBySession[$session->id])) {
                            $fail("Only {$this->availableSessionCapacity($session->fresh())} seats left for the selected session.");
                        }
                        $sessionReserved[$session->id] = true;
                        $reservations[] = ['model' => $session, 'qty' => $requestedBySession[$session->id]];
                    }
                } elseif ($ticket->isAddOn() && $ticket->sessions->where('is_active', true)->count() > 1) {
                    $fail("Please choose a session for {$ticket->slug}.");
                }

                $selectedDay = null;
                if ($ticket->offersDaySelection()) {
                    $chosen = $item['selected_event_day_id'] ?? null;
                    if (! $chosen) {
                        $fail("Please choose a day for {$ticket->slug}.");
                    }
                    $selectedDay = $ticket->validDays->firstWhere('id', (int) $chosen);
                    if (! $selectedDay) {
                        $fail('Selected day is invalid for this ticket.');
                    }
                }

                $unit = (float) $phase->price;
                $subtotal += $unit * $qty;

                $resolved[] = compact('ticket', 'qty', 'phase', 'session', 'selectedDay', 'unit');
            }

            $order = TicketOrder::create([
                'event_id' => $event->id,
                'user_id' => $buyerUser?->id,
                'status' => TicketOrderStatus::PendingPayment,
                'buyer_name' => $data['buyer_name'] ?? null,
                'buyer_email' => $data['buyer_email'] ?? null,
                'buyer_phone' => $data['buyer_phone'] ?? null,
                'subtotal' => $subtotal,
                'discount_amount' => 0,
                'total' => $subtotal,
                'payment_expires_at' => now()->addSeconds((int) config('xendit.invoice_duration', 86400)),
                'magic_link_expires_at' => now()->addYears(2),
                'source' => $data['source'] ?? 'public',
                'return_origin' => $this->resolveReturnOrigin($data['origin'] ?? null),
                'ip_address' => $data['ip_address'] ?? null,
                'user_agent' => $data['user_agent'] ?? null,
            ]);

            if ($idempotencyKey !== null) {
                $order->forceFill(['idempotency_key' => $idempotencyKey])->save();
            }

            $attendeeNumber = 0;
            $alsoAttending = (bool) ($data['also_attending'] ?? false);
            $totalQty = array_sum(array_column($resolved, 'qty'));
            $buyerAttendee = null;
            $firstAttendee = null;

            foreach ($resolved as $row) {
                $orderItem = $order->items()->create([
                    'ticket_id' => $row['ticket']->id,
                    'ticket_session_id' => $row['session']?->id,
                    'selected_event_day_id' => $row['selectedDay']?->id,
                    'quantity' => $row['qty'],
                    'unit_price' => $row['unit'],
                    'phase_label' => $row['phase']->label,
                    'ticket_price_phase_id' => $row['phase']->id,
                    'subtotal' => $row['unit'] * $row['qty'],
                ]);

                for ($i = 0; $i < $row['qty']; $i++) {
                    $attendeeNumber++;
                    $isBuyer = $alsoAttending && $attendeeNumber === 1;

                    // Placeholder names use the buyer's name (e.g. "Antonius #2"),
                    // not a generic "Tamu #n" - the buyer recognizes their group.
                    $attendee = $orderItem->attendees()->create([
                        'ticket_id' => $row['ticket']->id,
                        'name' => $this->attendeeDisplayName($data['buyer_name'] ?? null, $attendeeNumber, $totalQty),
                        'email' => $isBuyer ? ($data['buyer_email'] ?? null) : null,
                        'phone' => $isBuyer ? ($data['buyer_phone'] ?? null) : null,
                        'claimed_by_user_id' => $isBuyer ? $buyerUser?->id : null,
                        'personalized_at' => $isBuyer ? now() : null,
                    ]);

                    $firstAttendee ??= $attendee;
                    if ($isBuyer) {
                        $buyerAttendee ??= $attendee;
                    }
                }

                // sold_count/booked_count are already incremented by the
                // atomic reserve() calls in the validation loop above (once
                // per unique ticket/phase/session id, for the full aggregated
                // quantity) - no further increment needed here.
            }

            // Registration answers are per-attendee; at checkout the buyer
            // answers for their own ticket (attendee #1). Other attendees fill
            // theirs via the manage/personalize surfaces after checkout.
            if (! empty($data['registration']['responses'])) {
                $this->storeRegistrationResponses(
                    $buyerAttendee ?? $firstAttendee,
                    $event,
                    (array) $data['registration']['responses'],
                );
            }

            // Access code + promo + final total (tickets have no tax/service;
            // total = subtotal - discount). Apply the access code FIRST so its
            // adjustment gets the lower id and prices off the full base; the
            // promo then discounts the remainder (deterministic clamp order).
            activity()->withoutLogs(function () use ($data, $order, $accessCodeModel): void {
                if ($accessCodeModel) {
                    $this->accessCodes->apply(
                        $accessCodeModel,
                        $order->fresh(['items', 'adjustments']),
                        $data['buyer_email'] ?? null,
                        auth()->id(),
                    );
                    $order->forceFill(['access_code_applied' => strtoupper(trim((string) $data['access_code']))])->save();
                }

                if (! empty($data['promo_code'])) {
                    $this->promoCodes->applyByCode(
                        (string) $data['promo_code'],
                        $order->fresh(['items', 'adjustments.promotionRule']),
                        (string) ($data['buyer_email'] ?? ''),
                        auth()->id(),
                    );
                    $order->forceFill(['promo_code_applied' => strtoupper(trim((string) $data['promo_code']))])->save();
                }

                $this->pricing->recalculateAndPersist($order->fresh(['items', 'adjustments']));
            });

            return ['order' => $order->fresh(['items.attendees', 'event']), 'duplicate' => false];
        });

        // A duplicate submission returns the order exactly as first resolved -
        // running it through resolvePayment again would re-send confirmation
        // emails or open a second payment-gateway checkout for the same order.
        if ($result['duplicate']) {
            return $result['order'];
        }

        return $this->resolvePayment($result['order'], $data, $checkoutClient);
    }

    /**
     * Release every reservation recorded by createOrder()'s validation loop,
     * in insertion order, when a later line fails after earlier lines already
     * reserved their stock/phase/session counters.
     *
     * @param  array<int, array{model: Ticket|TicketPricePhase|TicketSession, qty: int}>  $reservations
     */
    protected function releaseReservations(array $reservations): void
    {
        foreach ($reservations as $reservation) {
            $reservation['model']->release($reservation['qty']);
        }
    }

    /**
     * Trim an optional client-supplied idempotency key down to null when
     * blank, so blank strings behave the same as an absent key.
     */
    protected function normalizeIdempotencyKey(?string $key): ?string
    {
        $key = trim((string) $key);

        return $key === '' ? null : $key;
    }

    /**
     * Free orders confirm immediately; paid orders open a gateway checkout.
     * The real gateway HTTP round-trip (~300-800ms) is deferred to
     * CreateTicketCheckoutJob so the buyer's request - and the PHP-FPM
     * worker handling it - is never held on it (Plan 017). A test-injected
     * $checkoutClient keeps the previous synchronous contract instead, so
     * existing coverage can assert on the resulting order without touching
     * the queue.
     *
     * @param  array<string, mixed>  $data
     */
    protected function resolvePayment(TicketOrder $order, array $data, ?CreatesCheckout $checkoutClient = null): TicketOrder
    {
        if ((float) $order->total <= 0) {
            $order->update([
                'total' => 0,
                'status' => TicketOrderStatus::Confirmed,
                'paid_at' => now(),
                'payment_expires_at' => null,
            ]);

            // Free/Claim orders skip the webhook, so consume the access-code hold here.
            $this->accessCodes->consume($order);

            $this->dispatchConfirmationEmails($order);

            return $order->fresh(['items.attendees', 'event']);
        }

        if ($checkoutClient !== null) {
            $this->attemptTicketCheckout($order, $checkoutClient);

            return $order->fresh(['items.attendees', 'event']);
        }

        CreateTicketCheckoutJob::dispatch($order->id)->afterCommit();

        return $order->fresh(['items.attendees', 'event']);
    }

    /**
     * Open a payment-gateway checkout for a pending paid order and persist
     * the resulting payment_url/reference. Idempotent - a no-op once the
     * order is no longer PendingPayment or already carries a payment_url,
     * so a retried job (or a webhook landing first) never clobbers it or
     * opens a duplicate checkout. Throws on gateway failure so a queued
     * caller can decide whether to retry; attemptTicketCheckout() below
     * is the "never blow up the caller" wrapper used for the synchronous
     * (test-injected client) contract.
     */
    public function openTicketCheckout(TicketOrder $order, ?CreatesCheckout $checkoutClient = null): void
    {
        if ($order->status !== TicketOrderStatus::PendingPayment || $order->payment_url !== null) {
            return;
        }

        // Provider-agnostic: use whichever gateway the project has active
        // (Xendit OR Midtrans), exactly like hotel reservations. The
        // per-event channel allowlist + checkout-method dispatch live inside
        // the payable / provider, so this path stays provider-neutral. The
        // reference is stored in `xendit_invoice_id` (a generic column); the
        // webhook matches the order by `order_number`, not this id.
        $gateway = $order->event?->project?->activePaymentGateway();
        abort_unless($gateway, 422, 'No active payment gateway configured for this project.');

        $client = $checkoutClient ?? app(PaymentProviderFactory::class)->make($gateway);
        [$successUrl, $failureUrl] = $this->paymentRedirectUrls($order);

        $checkout = $client->createCheckout($order, $successUrl, $failureUrl);

        $order->update([
            'xendit_invoice_id' => $checkout['reference'],
            'payment_url' => $checkout['payment_url'],
            'payment_gateway_id' => $client->gateway()?->id ?? $gateway->id,
        ]);
    }

    /**
     * Synchronous wrapper around openTicketCheckout() that swallows a
     * gateway failure (logs it via XenditErrorMapper) instead of throwing,
     * preserving the original resolvePayment() contract: the order stays
     * PendingPayment with no payment_url, ready for a later retry.
     */
    protected function attemptTicketCheckout(TicketOrder $order, ?CreatesCheckout $checkoutClient = null): void
    {
        try {
            $this->openTicketCheckout($order, $checkoutClient);
        } catch (\Throwable $e) {
            $this->logCheckoutFailure($order, $e);
        }
    }

    /**
     * Log a gateway checkout failure via XenditErrorMapper. Shared by the
     * synchronous contract (attemptTicketCheckout) and CreateTicketCheckoutJob.
     */
    public function logCheckoutFailure(TicketOrder $order, \Throwable $e): void
    {
        $mapped = XenditErrorMapper::map($e);
        Log::log($mapped['log_level'], 'Ticket payment link creation failed - order kept for retry', [
            'ticket_order_id' => $order->id,
            'order_number' => $order->order_number,
            'error_code' => $mapped['error_code'],
            'raw_error' => $e->getMessage(),
        ]);
    }

    /**
     * Mark a pending order confirmed (paid). Idempotent — only the first
     * concurrent webhook flips the status. Returns whether THIS call actually
     * performed the flip, so callers (e.g. a manual mark-paid) can tell a real
     * confirmation apart from a race already won by a webhook.
     */
    public function markAsConfirmed(TicketOrder $order, array $payload = []): bool
    {
        if ($order->status === TicketOrderStatus::Confirmed) {
            return false;
        }

        $update = [
            'status' => TicketOrderStatus::Confirmed,
            'paid_at' => now(),
            'xendit_invoice_id' => $payload['id'] ?? $order->xendit_invoice_id,
        ];

        if (! empty($payload['payment_channel'])) {
            $update['payment_channel'] = $payload['payment_channel'];
        } elseif (! empty($payload['bank_code'])) {
            $update['payment_channel'] = $payload['bank_code'];
        }

        $confirmed = TicketOrder::query()
            ->whereKey($order->id)
            ->where('status', TicketOrderStatus::PendingPayment->value)
            ->update($update);

        if ($confirmed > 0) {
            $this->accessCodes->consume($order);
            $this->dispatchConfirmationEmails($order);
        }

        return $confirmed > 0;
    }

    /**
     * Decide which post-confirmation emails to send. The buyer always receives
     * exactly ONE email:
     *  - A single-ticket self-purchase (the buyer is the only attendee) gets one
     *    consolidated e-ticket - QR plus order summary.
     *  - Every other order sends the buyer one order confirmation; when the buyer
     *    is also attending it carries their personal QR inline, so they never get
     *    a separate e-ticket on top of it.
     * A personal e-ticket (with QR) goes only to OTHER attendees who have their
     * own email - the buyer's own ticket is already covered by the confirmation.
     */
    protected function dispatchConfirmationEmails(TicketOrder $order): void
    {
        $order->loadMissing('attendees');
        $attendees = $order->attendees;
        $buyerEmail = strtolower(trim((string) $order->buyer_email));

        if ($attendees->count() === 1) {
            $only = $attendees->first();
            if ($buyerEmail !== '' && strtolower(trim((string) $only->email)) === $buyerEmail) {
                SendAttendeeETicketJob::dispatch($only->id, consolidated: true);

                return;
            }
        }

        if ($buyerEmail !== '') {
            SendTicketOrderConfirmationJob::dispatch($order->id);
        }

        foreach ($attendees as $attendee) {
            $attendeeEmail = strtolower(trim((string) $attendee->email));
            if ($attendeeEmail !== '' && $attendeeEmail !== $buyerEmail) {
                SendAttendeeETicketJob::dispatch($attendee->id);
            }
        }
    }

    /**
     * Expire a pending order on payment timeout and release the held inventory
     * counters + promo usage.
     */
    public function expireOrder(TicketOrder $order): void
    {
        if ($order->status !== TicketOrderStatus::PendingPayment) {
            return;
        }

        DB::transaction(function () use ($order) {
            // Atomic conditional flip: only the first caller (expiry job OR the
            // Xendit "expired" webhook) wins, so the inventory + promo + access
            // releases below run exactly once. Fixes the TOCTOU double-decrement.
            $flipped = TicketOrder::query()
                ->whereKey($order->id)
                ->where('status', TicketOrderStatus::PendingPayment->value)
                ->update(['status' => TicketOrderStatus::Expired->value]);

            if ($flipped === 0) {
                return;
            }

            $this->promoCodes->voidAllOnCancel($order);
            $this->accessCodes->release($order);

            foreach ($order->items as $item) {
                Ticket::whereKey($item->ticket_id)->where('sold_count', '>=', $item->quantity)->decrement('sold_count', $item->quantity);
                if ($item->ticket_session_id) {
                    TicketSession::whereKey($item->ticket_session_id)->where('booked_count', '>=', $item->quantity)->decrement('booked_count', $item->quantity);
                }
                // The attendee-refund path (refundAttendee()) releases the
                // phase sold_count for a single voided seat; this covers the
                // whole-order expiry path.
                if ($item->ticket_price_phase_id) {
                    TicketPricePhase::whereKey($item->ticket_price_phase_id)
                        ->where('sold_count', '>=', $item->quantity)
                        ->decrement('sold_count', $item->quantity);
                }
            }

            // Release the event-level total-headcount hold this order made at
            // createOrder() time, mirroring the per-ticket releases above.
            $totalQty = $order->items->sum('quantity');
            if ($totalQty > 0) {
                Event::whereKey($order->event_id)
                    ->where('reserved_count', '>=', $totalQty)
                    ->decrement('reserved_count', $totalQty);
            }

            $order->setAttribute('status', TicketOrderStatus::Expired);
        });
    }

    /**
     * Void a single attendee's seat: cancel it, rotate its `qr_token` (killing
     * the old QR so a transferred/refunded badge stops scanning), release its
     * seat (order-item quantity + ticket/session/phase `sold_count` + the
     * event's total-headcount `reserved_count`), and recompute the order
     * total. Idempotent - a no-op when the attendee is already cancelled.
     * Never touches the order's own status: the order stays Confirmed with
     * its remaining attendees still valid. Called per-attendee by
     * refundOrder() for a whole-order refund too.
     */
    public function refundAttendee(Attendee $attendee, ?string $reason = null, ?int $staffId = null): void
    {
        DB::transaction(function () use ($attendee, $reason, $staffId) {
            $locked = Attendee::query()->whereKey($attendee->id)->lockForUpdate()->first();

            if (! $locked || $locked->cancelled_at !== null) {
                return;
            }

            $locked->forceFill([
                'cancelled_at' => now(),
                'cancelled_reason' => $reason,
                'cancelled_by' => $staffId,
                'qr_token' => (string) Str::ulid(),
            ])->save();

            $item = TicketOrderItem::query()->whereKey($locked->ticket_order_item_id)->lockForUpdate()->first();

            if (! $item) {
                return;
            }

            $newQuantity = max(0, $item->quantity - 1);
            $item->update([
                'quantity' => $newQuantity,
                'subtotal' => (float) $item->unit_price * $newQuantity,
            ]);

            Ticket::whereKey($item->ticket_id)->where('sold_count', '>=', 1)->decrement('sold_count');

            if ($item->ticket_session_id) {
                TicketSession::whereKey($item->ticket_session_id)->where('booked_count', '>=', 1)->decrement('booked_count');
            }

            if ($item->ticket_price_phase_id) {
                TicketPricePhase::whereKey($item->ticket_price_phase_id)->where('sold_count', '>=', 1)->decrement('sold_count');
            }

            $order = TicketOrder::query()->whereKey($item->ticket_order_id)->lockForUpdate()->first();

            if (! $order) {
                return;
            }

            // One seat's worth of the event-level total-headcount hold this
            // order made at createOrder() time - mirrors the ticket/session/
            // phase releases above.
            Event::whereKey($order->event_id)->where('reserved_count', '>=', 1)->decrement('reserved_count');

            // The order's `subtotal` is the sum of every item's own subtotal
            // (never recomputed from live items elsewhere - see createOrder),
            // so shaving off exactly the released seat's unit price keeps that
            // invariant intact before the pricing engine re-derives the
            // discount + total against the smaller base.
            $order->forceFill([
                'subtotal' => max(0.0, (float) $order->subtotal - (float) $item->unit_price),
            ])->save();

            $this->pricing->recalculateAndPersist($order->fresh(['items', 'adjustments']));
        });
    }

    /**
     * Refund an entire ticket order: flip Confirmed -> Refunded and void every
     * still-active attendee via refundAttendee() (rotates each qr_token,
     * releases every ticket/session/phase counter, recomputes the total down
     * to what remains). Idempotent - the atomic conditional flip means a
     * redelivered refund webhook that finds the order already Refunded is a
     * no-op.
     */
    public function refundOrder(TicketOrder $order, ?string $reason = null, ?int $staffId = null): void
    {
        DB::transaction(function () use ($order, $reason, $staffId) {
            $flipped = TicketOrder::query()
                ->whereKey($order->id)
                ->where('status', TicketOrderStatus::Confirmed->value)
                ->update(['status' => TicketOrderStatus::Refunded->value]);

            if ($flipped === 0) {
                return;
            }

            $attendees = Attendee::query()
                ->whereHas('ticketOrderItem', fn ($q) => $q->where('ticket_order_id', $order->id))
                ->whereNull('cancelled_at')
                ->get();

            foreach ($attendees as $attendee) {
                $this->refundAttendee($attendee, $reason, $staffId);
            }

            $order->setAttribute('status', TicketOrderStatus::Refunded);
        });
    }

    /**
     * Honor a genuine paid event that lands AFTER the order was already flipped
     * to Expired (Trigger A: a slow bank transfer / retail channel settles
     * minutes after the 15-min hard-expiry job released the seat). Re-checks
     * LIVE availability for every line before resurrecting the order — the
     * conservative, no-oversell mitigation:
     *   - Every line still fits -> atomically flip Expired -> Confirmed, restore
     *     the sold_count/booked_count counters this order released on expiry,
     *     consume the access-code hold, and dispatch the normal confirmation
     *     emails. Returns 'reconfirmed'.
     *   - Any line has since been resold to someone else -> do NOT oversell.
     *     Record the paid event (`paid_after_expiry_at` + a
     *     `payment_needs_reconciliation` activity log) so staff can resolve it
     *     by hand; the order stays Expired. Returns 'needs_reconciliation'.
     * Idempotent: the atomic conditional update()'s affected-row count gates
     * the counter re-increment, so a redelivered webhook cannot double-count.
     * Callers should only invoke this for an order that is not already
     * Confirmed (they check that first so a duplicate paid event short-circuits
     * before reaching here).
     *
     * Event capacity (plan 021): the pre-flip availability check also verifies
     * the event's total-headcount still fits - expireOrder() released this
     * order's `reserved_count`, and the freed slots may have been taken while
     * it sat expired, so insufficient headroom routes to needs_reconciliation
     * exactly like per-ticket stock does. On a successful resurrection the
     * `reserved_count` is restored, symmetric with the ticket/session/phase
     * restores below.
     *
     * @param  array<string, mixed>  $payload
     * @return 'reconfirmed'|'needs_reconciliation'|'not_expired'|'already_final'
     */
    public function reconfirmAfterExpiry(TicketOrder $order, array $payload = []): string
    {
        if ($order->status !== TicketOrderStatus::Expired) {
            return 'not_expired';
        }

        $order->loadMissing('items.ticket', 'items.ticketSession');

        $requestedByTicket = [];
        $requestedBySession = [];
        foreach ($order->items as $item) {
            $requestedByTicket[$item->ticket_id] = ($requestedByTicket[$item->ticket_id] ?? 0) + $item->quantity;
            if ($item->ticket_session_id) {
                $requestedBySession[$item->ticket_session_id] = ($requestedBySession[$item->ticket_session_id] ?? 0) + $item->quantity;
            }
        }

        foreach ($order->items as $item) {
            $ticket = $item->ticket;
            $ticketId = $item->ticket_id;

            if (! $ticket) {
                $this->recordNeedsReconciliation($order, $payload);

                return 'needs_reconciliation';
            }

            $available = $this->availableStock($ticket);
            if ($available !== null && $available < $requestedByTicket[$ticketId]) {
                $this->recordNeedsReconciliation($order, $payload);

                return 'needs_reconciliation';
            }

            if ($item->ticket_session_id) {
                $session = $item->ticketSession;
                if (! $session) {
                    $this->recordNeedsReconciliation($order, $payload);

                    return 'needs_reconciliation';
                }

                $left = $this->availableSessionCapacity($session);
                if ($left !== null && $left < $requestedBySession[$item->ticket_session_id]) {
                    $this->recordNeedsReconciliation($order, $payload);

                    return 'needs_reconciliation';
                }
            }
        }

        // Event-level headcount must also still fit: expireOrder() released
        // this order's reserved_count, and the freed slots may have been taken
        // while it sat expired. Mirror the per-ticket stock check above.
        $totalQty = $order->items->sum('quantity');
        if ($totalQty > 0) {
            $event = Event::find($order->event_id);
            if ($event && $event->capacity !== null
                && ($event->capacity - $event->reserved_count) < $totalQty) {
                $this->recordNeedsReconciliation($order, $payload);

                return 'needs_reconciliation';
            }
        }

        $update = [
            'status' => TicketOrderStatus::Confirmed,
            'paid_at' => now(),
            'xendit_invoice_id' => $payload['id'] ?? $order->xendit_invoice_id,
        ];

        if (! empty($payload['payment_channel'])) {
            $update['payment_channel'] = $payload['payment_channel'];
        }

        $flipped = TicketOrder::query()
            ->whereKey($order->id)
            ->where('status', TicketOrderStatus::Expired->value)
            ->update($update);

        if ($flipped === 0) {
            // Another concurrent delivery of the same event already resolved
            // this order (reconfirmed or recorded) between our read above and
            // this write.
            return 'already_final';
        }

        foreach ($order->items as $item) {
            $item->ticket?->increment('sold_count', $item->quantity);
            $item->ticketSession?->increment('booked_count', $item->quantity);
            // Symmetric with the phase release in expireOrder() - a resurrected
            // order must restore the exact phase's sold_count it gave back.
            if ($item->ticket_price_phase_id) {
                TicketPricePhase::whereKey($item->ticket_price_phase_id)->increment('sold_count', $item->quantity);
            }
        }

        // Symmetric with the event-headcount release in expireOrder(): a
        // resurrected order restores the reserved_count it gave back.
        if ($totalQty > 0) {
            Event::whereKey($order->event_id)->increment('reserved_count', $totalQty);
        }

        $this->accessCodes->consume($order);
        $this->dispatchConfirmationEmails($order);

        return 'reconfirmed';
    }

    /**
     * Record a paid-after-expiry event that could not be safely resurrected
     * (stock/session capacity no longer available). Idempotent — only the
     * first call stamps the timestamp, so a redelivered webhook does not
     * overwrite it.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function recordNeedsReconciliation(TicketOrder $order, array $payload): void
    {
        TicketOrder::query()
            ->whereKey($order->id)
            ->whereNull('paid_after_expiry_at')
            ->update(['paid_after_expiry_at' => now()]);

        activity()
            ->performedOn($order)
            ->event('payment_needs_reconciliation')
            ->withProperties([
                'project_id' => $order->event?->project_id,
                'ticket_order_id' => $order->id,
                'invoice_id' => $payload['id'] ?? null,
                'amount' => $payload['amount'] ?? null,
            ])
            ->log('Ticket payment received after expiry, but stock is no longer available - needs manual reconciliation');
    }

    /**
     * Admin "Bulk Generate": create a complimentary batch order (free, confirmed,
     * source=admin, OUTSIDE sale stock) and hand attendee issuance off to a queued
     * job so big batches can stream in with progress. Returns the order immediately.
     *
     * STOP CONDITION (plan 021, event capacity): intentionally does NOT
     * reserve against the event's total-headcount `capacity`/`reserved_count`
     * - comps stay outside sale stock exactly like they stay outside
     * per-ticket stock (see availableStock()'s docblock). Whether a comp
     * attendee should still count toward the fire-code/venue headcount (they
     * DO physically occupy the room even though they bypass sale stock) is a
     * semantics decision left for the reviewer; wiring it here would need the
     * same atomic reserve + release-on-failure handling as createOrder().
     *
     * @param  array<string, mixed>  $data
     */
    public function bulkGenerate(array $data): TicketOrder
    {
        $event = Event::findOrFail($data['event_id']);
        abort_unless($event->tickets_enabled, 422, 'Ticketing is not enabled for this event.');

        return DB::transaction(function () use ($data, $event) {
            $ticket = Ticket::query()
                ->where('id', $data['ticket_id'])
                ->where('event_id', $event->id)
                ->where('is_active', true)
                ->where('purchase_type', PurchaseType::FirstParty->value)
                ->with(['pricePhases', 'validDays', 'sessions'])
                ->firstOrFail();

            // Structural validation only - comps bypass stock/capacity entirely.
            $session = null;
            if (! empty($data['ticket_session_id'])) {
                $session = TicketSession::query()
                    ->where('id', $data['ticket_session_id'])
                    ->where('ticket_id', $ticket->id)
                    ->first();
                abort_if(! $session, 422, 'Selected session is invalid for this ticket.');
            } elseif ($ticket->isAddOn() && $ticket->sessions->where('is_active', true)->count() > 1) {
                abort(422, "Please choose a session for {$ticket->slug}.");
            }

            $selectedDay = null;
            if ($ticket->offersDaySelection()) {
                $chosen = $data['selected_event_day_id'] ?? null;
                abort_if(! $chosen, 422, "Please choose a day for {$ticket->slug}.");
                $selectedDay = $ticket->validDays->firstWhere('id', (int) $chosen);
                abort_if(! $selectedDay, 422, 'Selected day is invalid for this ticket.');
            }

            $count = ($data['mode'] ?? 'anonymous') === 'named'
                ? count($data['recipients'] ?? [])
                : (int) ($data['quantity'] ?? 0);
            abort_if($count < 1, 422, 'Nothing to generate.');

            // Comps can be issued off-sale: capture the active phase label if any.
            $phase = $this->resolveActivePhase($ticket);

            $order = TicketOrder::create([
                'event_id' => $event->id,
                'user_id' => null,
                'status' => TicketOrderStatus::Confirmed,
                'subtotal' => 0,
                'discount_amount' => 0,
                'total' => 0,
                'paid_at' => now(),
                'payment_expires_at' => null,
                'magic_link_expires_at' => now()->addYears(2),
                'source' => 'admin',
                'batch_label' => $data['batch_label'] ?? null,
                'batch_status' => 'processing',
                'notes' => $data['reason'] ?? null,
            ]);

            $order->items()->create([
                'ticket_id' => $ticket->id,
                'ticket_session_id' => $session?->id,
                'selected_event_day_id' => $selectedDay?->id,
                'quantity' => $count,
                'unit_price' => 0,
                'phase_label' => $phase?->label ?? 'Complimentary',
                'subtotal' => 0,
            ]);

            // Dispatch AFTER the transaction commits, otherwise the worker can pick
            // the job up before the order row is visible (or while it is still locked).
            GenerateBulkAttendeesJob::dispatch($order->id, [
                'mode' => $data['mode'] ?? 'anonymous',
                'quantity' => $count,
                'label_prefix' => $data['label_prefix'] ?? null,
                'recipients' => $data['recipients'] ?? [],
                'delivery' => $data['delivery'] ?? 'generate_only',
            ])->afterCommit();

            return $order;
        });
    }

    /**
     * Issue the attendees for a bulk batch. Run by GenerateBulkAttendeesJob WITHOUT
     * a wrapping transaction so the attendee count grows incrementally (progress
     * polling). Does NOT touch sold_count/booked_count (stock bypass).
     *
     * @param  array<string, mixed>  $spec
     */
    public function generateAttendeesForBatch(TicketOrder $order, array $spec): void
    {
        $item = $order->items()->first();
        if (! $item) {
            $order->update(['batch_status' => 'failed']);

            return;
        }

        $delivery = $spec['delivery'] ?? 'generate_only';

        try {
            if (($spec['mode'] ?? 'anonymous') === 'named') {
                foreach (array_chunk($spec['recipients'] ?? [], 200) as $chunk) {
                    foreach ($chunk as $recipient) {
                        $email = trim((string) ($recipient['email'] ?? ''));
                        $user = $email !== ''
                            ? $this->resolveBuyerUser(['buyer_email' => $email, 'buyer_name' => $recipient['name'] ?? null])
                            : null;

                        $attendee = $item->attendees()->create([
                            'ticket_id' => $item->ticket_id,
                            'name' => $recipient['name'] ?? null,
                            'email' => $email !== '' ? $email : null,
                            'claimed_by_user_id' => $user?->id,
                            'personalized_at' => now(),
                        ]);

                        if ($delivery === 'auto_email' && $email !== '') {
                            SendAttendeeETicketJob::dispatch($attendee->id);
                        }
                    }
                }
            } else {
                $prefix = $spec['label_prefix'] ?: 'Tamu';
                $total = (int) ($spec['quantity'] ?? 0);
                for ($index = 1; $index <= $total; $index++) {
                    $item->attendees()->create([
                        'ticket_id' => $item->ticket_id,
                        'name' => $this->attendeeDisplayName($prefix, $index, $total),
                    ]);
                }
            }

            $order->update(['batch_status' => 'completed']);
        } catch (\Throwable $e) {
            $order->update(['batch_status' => 'failed']);

            throw $e;
        }
    }

    /**
     * Placeholder display name for an issued attendee: "{base} #n" within a group,
     * just "{base}" for a single ticket, falling back to "Tamu #n" with no base.
     */
    protected function attendeeDisplayName(?string $base, int $index, int $total): ?string
    {
        $base = trim((string) $base);

        if ($base === '') {
            return "Tamu #{$index}";
        }

        return $total > 1 ? "{$base} #{$index}" : $base;
    }

    /**
     * Find or lazily create the buyer's User (Visitor) by email. Never sets a
     * password (login is via magic link later).
     *
     * @param  array<string, mixed>  $data
     */
    protected function resolveBuyerUser(array $data): ?User
    {
        $email = strtolower(trim((string) ($data['buyer_email'] ?? '')));
        if ($email === '') {
            return null;
        }

        $user = User::withTrashed()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($user) {
            if ($user->trashed()) {
                $user->restore();
            }

            return $user;
        }

        return User::create([
            'name' => $data['buyer_name'] ?? Str::before($email, '@'),
            'email' => $email,
            'phone' => $data['buyer_phone'] ?? null,
            'username' => $this->uniqueUsername($email),
            'status' => 'active',
            'visibility' => 'private',
        ]);
    }

    /**
     * Persist the buyer's business-matching opt-in + answers to their User
     * profile (answers are per-User, shared with the dashboard profile).
     *
     * @param  array<string, mixed>  $bm
     */
    protected function storeBusinessMatching(User $user, Event $event, array $bm): void
    {
        // Ignore any BM payload when the event has no Business Matching program.
        if (! $event->business_matching_enabled) {
            return;
        }

        $optIn = (bool) ($bm['opt_in'] ?? false);
        $user->forceFill(['business_matching_opt_in' => $optIn])->save();

        if (! $optIn) {
            return;
        }

        $fields = $event->eventCustomFields()->where('is_active', true)->get();

        // Posted ids resolve by id OR legacy_id: checkouts opened before the
        // custom-fields migration deploy still post old event_custom_fields ids.
        $values = [];
        foreach ((array) ($bm['responses'] ?? []) as $resp) {
            $fieldId = (int) ($resp['custom_field_id'] ?? 0);
            $field = $fields->first(fn ($f) => $f->id === $fieldId || $f->legacy_id === $fieldId);
            if ($field !== null) {
                $values[(string) $field->id] = $resp['value'] ?? null;
            }
        }

        CustomFieldValues::store($user, $fields, $values, 'id');
    }

    /**
     * Persist the buyer's registration answers onto their attendee row.
     * Unknown ulids are dropped; scalars are array-wrapped by the store.
     *
     * @param  array<string, mixed>  $responses
     */
    protected function storeRegistrationResponses(?Attendee $attendee, Event $event, array $responses): void
    {
        if ($attendee === null) {
            return;
        }

        $fields = $event->registrationFields()->where('is_active', true)->get();

        CustomFieldValues::store($attendee, $fields, $responses, 'ulid');
    }

    protected function uniqueUsername(string $email): string
    {
        $base = Str::slug(Str::before($email, '@')) ?: 'visitor';
        $candidate = $base;
        $i = 1;
        while (User::query()->where('username', $candidate)->exists()) {
            $candidate = $base.'-'.$i++;
        }

        return $candidate;
    }

    /**
     * Validate the originating site URL (against the trusted-host allowlist) so
     * the post-payment bouncer can redirect the buyer back to the exact event
     * domain they bought from. Mirrors ReservationService::resolveReturnOrigin.
     */
    protected function resolveReturnOrigin(?string $origin): string
    {
        $fallback = rtrim((string) config('app.frontend_url'), '/');

        if (! is_string($origin) || trim($origin) === '') {
            return $fallback;
        }

        $host = parse_url($origin, PHP_URL_HOST);
        $scheme = parse_url($origin, PHP_URL_SCHEME);

        if (! $host || ! $scheme || ! in_array($host, (array) config('payment.trusted_redirect_hosts', []), true)) {
            return $fallback;
        }

        $port = parse_url($origin, PHP_URL_PORT);

        return strtolower($scheme).'://'.$host.($port ? ':'.$port : '');
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function paymentRedirectUrls(TicketOrder $order): array
    {
        $bouncer = rtrim((string) config('app.url'), '/').'/payment/redirect';
        $ref = urlencode($order->order_number);

        return [
            $bouncer.'?order_id='.$ref.'&result=success',
            $bouncer.'?order_id='.$ref.'&result=failed',
        ];
    }
}
