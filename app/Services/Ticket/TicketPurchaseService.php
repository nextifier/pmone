<?php

namespace App\Services\Ticket;

use App\Contracts\Payment\CreatesCheckout;
use App\Enums\Ticketing\PurchaseType;
use App\Enums\Ticketing\TicketOrderStatus;
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
     * Quantity still sellable for a ticket: stock minus committed (confirmed or
     * still-held pending) order items. Null stock = unlimited.
     */
    public function availableStock(Ticket $ticket): ?int
    {
        if ($ticket->stock === null) {
            return null;
        }

        return max(0, $ticket->stock - $this->committedQuantity('ticket_id', $ticket->id));
    }

    /**
     * Remaining capacity of an add-on session, or null when uncapped.
     */
    public function availableSessionCapacity(TicketSession $session): ?int
    {
        if ($session->capacity === null) {
            return null;
        }

        return max(0, $session->capacity - $this->committedQuantity('ticket_session_id', $session->id));
    }

    protected function committedQuantity(string $column, int $id): int
    {
        return (int) DB::table('ticket_order_items as toi')
            ->join('ticket_orders as o', 'toi.ticket_order_id', '=', 'o.id')
            ->where("toi.{$column}", $id)
            // Admin comp batches (bulkGenerate) are documented as OUTSIDE sale
            // stock; `source` is a non-nullable string so this excludes them
            // cleanly with no NULL edge case.
            ->where('o.source', '!=', 'admin')
            ->whereNull('toi.deleted_at')
            ->whereNull('o.deleted_at')
            ->whereIn('o.status', [
                TicketOrderStatus::Confirmed->value,
                // Any still-pending order holds its seat until its status
                // actually flips (webhook confirms it OR the expiry job/webhook
                // expires it). The old `payment_expires_at > now()` narrowing
                // created a soft-expiry gap: the moment the clock lapsed the
                // seat was released for resale even though the order was still
                // PendingPayment (the hard-expiry job runs every 15 min), so a
                // genuine late payment on the first order could land on a seat
                // already sold to someone else.
                TicketOrderStatus::PendingPayment->value,
            ])
            ->sum('toi.quantity');
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

        foreach ($items as $item) {
            $ticket = $event->tickets()
                ->with('pricePhases')
                ->where('id', $item['ticket_id'])
                ->first();

            if (! $ticket || $ticket->purchase_type !== PurchaseType::FirstParty) {
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

            $stockChecked = [];
            $sessionCapacityChecked = [];

            foreach ($items as $idx => $item) {
                $ticket = Ticket::query()
                    ->where('id', $item['ticket_id'])
                    ->where('event_id', $event->id)
                    ->where('is_active', true)
                    ->where('purchase_type', PurchaseType::FirstParty->value)
                    ->with(['pricePhases', 'validDays'])
                    ->lockForUpdate()
                    ->first();

                abort_if(! $ticket, 422, "Ticket #{$idx} is invalid or not on sale.");

                // Gated tickets (hidden/code_required) require a valid access code
                // that unlocks them. Public tickets are unrestricted.
                if ($ticket->isGated()) {
                    abort_if(
                        ! $accessCodeModel || ! $accessCodeModel->unlocksTicket($ticket->id),
                        422,
                        "Ticket {$ticket->slug} requires a valid access code.",
                    );
                }

                $qty = max(1, (int) ($item['quantity'] ?? 1));
                abort_if($ticket->min_quantity && $qty < $ticket->min_quantity, 422, "Minimum quantity for {$ticket->slug} is {$ticket->min_quantity}.");
                abort_if($ticket->max_quantity && $requestedByTicket[$ticket->id] > $ticket->max_quantity, 422, "Maximum quantity for {$ticket->slug} is {$ticket->max_quantity}.");

                $phase = $this->resolveActivePhase($ticket);
                abort_if(! $phase, 422, "Ticket {$ticket->slug} is not currently on sale.");

                // Checked once per ticket id (not per line) - the aggregated
                // requested quantity is the same on every line for this ticket,
                // so re-checking would just repeat the same comparison.
                if (! isset($stockChecked[$ticket->id])) {
                    $available = $this->availableStock($ticket);
                    abort_if($available !== null && $available < $requestedByTicket[$ticket->id], 422, "Only {$available} left for {$ticket->slug}.");
                    $stockChecked[$ticket->id] = true;
                }

                $session = null;
                if (! empty($item['ticket_session_id'])) {
                    $session = TicketSession::query()
                        ->where('id', $item['ticket_session_id'])
                        ->where('ticket_id', $ticket->id)
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->first();
                    abort_if(! $session, 422, 'Selected session is invalid for this ticket.');

                    if (! isset($sessionCapacityChecked[$session->id])) {
                        $sessionLeft = $this->availableSessionCapacity($session);
                        abort_if($sessionLeft !== null && $sessionLeft < $requestedBySession[$session->id], 422, "Only {$sessionLeft} seats left for the selected session.");
                        $sessionCapacityChecked[$session->id] = true;
                    }
                } elseif ($ticket->isAddOn() && $ticket->sessions->where('is_active', true)->count() > 1) {
                    abort(422, "Please choose a session for {$ticket->slug}.");
                }

                $selectedDay = null;
                if ($ticket->offersDaySelection()) {
                    $chosen = $item['selected_event_day_id'] ?? null;
                    abort_if(! $chosen, 422, "Please choose a day for {$ticket->slug}.");
                    $selectedDay = $ticket->validDays->firstWhere('id', (int) $chosen);
                    abort_if(! $selectedDay, 422, 'Selected day is invalid for this ticket.');
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

                $row['ticket']->increment('sold_count', $row['qty']);
                $row['phase']->increment('sold_count', $row['qty']);
                if ($row['session']) {
                    $row['session']->increment('booked_count', $row['qty']);
                }
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

        try {
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
        } catch (\Throwable $e) {
            $mapped = XenditErrorMapper::map($e);
            Log::log($mapped['log_level'], 'Ticket payment link creation failed - order kept for retry', [
                'ticket_order_id' => $order->id,
                'order_number' => $order->order_number,
                'error_code' => $mapped['error_code'],
                'raw_error' => $e->getMessage(),
            ]);
        }

        return $order->fresh(['items.attendees', 'event']);
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
            }

            $order->setAttribute('status', TicketOrderStatus::Expired);
        });
    }

    /**
     * Admin "Bulk Generate": create a complimentary batch order (free, confirmed,
     * source=admin, OUTSIDE sale stock) and hand attendee issuance off to a queued
     * job so big batches can stream in with progress. Returns the order immediately.
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
