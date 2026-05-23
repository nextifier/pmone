<?php

namespace App\Services\Reservation;

use App\Enums\PaymentMethod;
use App\Enums\PricingType;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Jobs\Reservation\SendBookingReceivedJob;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\HotelEvent;
use App\Models\HotelEventAllotment;
use App\Models\HotelTransferOption;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\RoomType;
use App\Services\Payment\PaymentGatewayResolver;
use App\Services\Pricing\PricingService;
use App\Services\Promotion\PenaltyService;
use App\Services\Promotion\PromoCodeService;
use App\Services\Xendit\XenditErrorMapper;
use App\Services\Xendit\XenditService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ReservationService
{
    public function __construct(
        protected XenditService $xendit,
        protected ?PaymentGatewayResolver $resolver = null,
        protected ?PricingService $pricing = null,
        protected ?PenaltyService $penalties = null,
        protected ?PromoCodeService $promoCodes = null,
    ) {
        $this->resolver ??= app(PaymentGatewayResolver::class);
        $this->pricing ??= app(PricingService::class);
        $this->penalties ??= app(PenaltyService::class);
        $this->promoCodes ??= app(PromoCodeService::class);
    }

    /**
     * Pick the Xendit client bound to the reservation's project gateway.
     * Aborts with 422 when the project has no active Xendit gateway configured.
     */
    protected function xenditFor(Reservation $reservation): XenditService
    {
        $project = $reservation->event?->project;

        if (! $project) {
            abort(422, 'Reservation must belong to an event with a project to generate payment.');
        }

        // Prefer the mode that matches the runtime environment, but fall back
        // to the opposite mode if only that one is configured. Staff can run
        // the production app against a test gateway during integration
        // verification without having to flip an env flag.
        $preferred = app()->environment('production') ? 'live' : 'test';
        $gateway = $project->resolvePaymentGateway('xendit', $preferred);

        if (! $gateway) {
            abort(422, "No active Xendit gateway configured for project \"{$project->username}\". Setup di Settings → Payment Gateways.");
        }

        return XenditService::forGateway($gateway);
    }

    /**
     * Check available quantity for a date range from allotments.
     * Returns remaining capacity (allotment quantity - already-committed reservations).
     * Hotel without allotment defaults to 0 (blocked) unless settings.allow_unlimited_booking is true.
     */
    public function checkAvailability(?int $eventId, int $hotelId, int $roomTypeId, string $checkIn, string $checkOut): int
    {
        $allotmentQuery = HotelEventAllotment::query()
            ->active()
            ->where('hotel_id', $hotelId)
            ->where('room_type_id', $roomTypeId)
            ->whereDate('start_date', '<=', $checkIn)
            ->whereDate('end_date', '>=', $checkOut);

        $hasAllotment = $allotmentQuery->exists();
        $totalQuantity = (int) $allotmentQuery->sum('quantity');

        if (! $hasAllotment) {
            $hotel = Hotel::query()->find($hotelId);
            $allowUnlimited = (bool) ($hotel?->settings['allow_unlimited_booking'] ?? false);

            return $allowUnlimited ? PHP_INT_MAX : 0;
        }

        // H2: Exclude PendingPayment whose payment_expires_at has lapsed — those
        // reservations no longer hold the room (will be expired by the next job tick).
        // Counting them as committed would phantom-block availability when the expire
        // worker is delayed. Paid/VoucherSent always commit regardless of expiry.
        $committed = (int) ReservationItem::query()
            ->whereHas('reservation', function ($q) use ($hotelId) {
                $q->where('hotel_id', $hotelId)
                    ->where(function ($inner) {
                        $inner->whereIn('status', [
                            ReservationStatus::Paid->value,
                            ReservationStatus::VoucherSent->value,
                        ])->orWhere(function ($pending) {
                            $pending->where('status', ReservationStatus::PendingPayment->value)
                                ->where('payment_expires_at', '>', now());
                        });
                    });
            })
            ->where('room_type_id', $roomTypeId)
            ->where(function ($q) use ($checkIn, $checkOut) {
                $q->where('check_in_date', '<', $checkOut)
                    ->where('check_out_date', '>', $checkIn);
            })
            ->sum('qty');

        return max(0, $totalQuantity - $committed);
    }

    /**
     * Create reservation: validate availability, lock, create reservation+items+transfers atomically.
     * Xendit invoice creation runs after the transaction commits, so a Xendit failure
     * leaves the reservation intact (with payment_url=null) for retry/manual setup.
     */
    public function createReservation(array $data, ?XenditService $xendit = null): Reservation
    {
        // Pre-flight: validate gateway availability when Xendit mode is requested.
        // Aborts here propagate to the client as 422 (vs caught silently inside the try-catch later).
        // Pre-flight gateway check is skipped when a promo_code is present because
        // a 100% discount will short-circuit Xendit and force Complimentary status.
        // If the promo turns out to be partial, Xendit invoice creation later will
        // fail gracefully (logged + reservation kept) without raising 422 here.
        $needsXendit = ! ($data['skip_payment'] ?? false)
            && ! ($data['mark_paid_manual'] ?? false)
            && ($data['generate_xendit'] ?? true)
            && empty($data['promo_code']);

        if ($needsXendit && $xendit === null) {
            if (empty($data['event_id'])) {
                abort(422, 'event_id is required when creating a reservation.');
            }
            $event = Event::with('project')->findOrFail($data['event_id']);
            $project = $event->project;

            if (! $project) {
                abort(422, 'Reservation must belong to an event with a project to generate payment.');
            }

            $preferred = app()->environment('production') ? 'live' : 'test';
            if (! $project->resolvePaymentGateway('xendit', $preferred)) {
                abort(422, "No active Xendit gateway configured for project \"{$project->username}\". Setup di Settings → Payment Gateways.");
            }
        }

        $reservation = DB::transaction(function () use ($data) {
            $hotel = Hotel::query()->findOrFail($data['hotel_id']);

            if (empty($data['event_id'])) {
                abort(422, 'event_id is required when creating a reservation.');
            }

            $pivot = HotelEvent::query()
                ->where(['hotel_id' => $hotel->id, 'event_id' => (int) $data['event_id'], 'is_active' => true])
                ->first();
            abort_if(! $pivot, 422, 'This hotel is not active for the requested event.');

            $items = $data['items'] ?? [];
            $transfers = $data['transfers'] ?? [];

            if (empty($items)) {
                abort(422, 'At least one room item is required.');
            }

            $subtotalRooms = 0;
            $subtotalTransfer = 0;
            $surchargeAmount = 0;
            $itemPayloads = [];

            foreach ($items as $item) {
                $roomType = RoomType::query()
                    ->where('id', $item['room_type_id'])
                    ->where('hotel_id', $hotel->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $checkIn = Carbon::parse($item['check_in_date']);
                $checkOut = Carbon::parse($item['check_out_date']);
                $nights = $checkIn->diffInDays($checkOut);

                if ($nights < 1) {
                    abort(422, 'Check-out must be after check-in.');
                }

                $qty = (int) ($item['qty'] ?? 1);

                // Acquire lock on overlapping allotments BEFORE availability check to prevent race condition.
                HotelEventAllotment::query()
                    ->active()
                    ->where('hotel_id', $hotel->id)
                    ->where('room_type_id', $roomType->id)
                    ->whereDate('start_date', '<=', $checkIn->toDateString())
                    ->whereDate('end_date', '>=', $checkOut->toDateString())
                    ->lockForUpdate()
                    ->get();

                $available = $this->checkAvailability(
                    $data['event_id'] ?? null,
                    $hotel->id,
                    $roomType->id,
                    $checkIn->toDateString(),
                    $checkOut->toDateString(),
                );

                if ($available < $qty) {
                    abort(422, "Not enough rooms available for {$roomType->name}. Only {$available} left.");
                }

                $allotment = HotelEventAllotment::query()
                    ->active()
                    ->where('hotel_id', $hotel->id)
                    ->where('room_type_id', $roomType->id)
                    ->whereDate('start_date', '<=', $checkIn->toDateString())
                    ->whereDate('end_date', '>=', $checkOut->toDateString())
                    ->orderBy('id')
                    ->first();

                $preview = $this->previewSubtotal($roomType, $checkIn, $checkOut, $qty, $allotment);

                $subtotal = $preview['subtotal'];
                $surchargeAmount += $preview['surcharge'];
                $subtotalRooms += $subtotal;

                $itemPayloads[] = [
                    'room_type_id' => $roomType->id,
                    'allotment_id' => $allotment?->id,
                    'check_in_date' => $checkIn->toDateString(),
                    'check_out_date' => $checkOut->toDateString(),
                    'nights' => $nights,
                    'qty' => $qty,
                    'guest_name' => $item['guest_name'] ?? null,
                    'guest_identity' => $item['guest_identity'] ?? null,
                    'rate_per_night' => $preview['rate_per_night_avg'],
                    'subtotal' => $subtotal,
                    'notes' => $item['notes'] ?? null,
                    'daily_breakdown' => $preview['daily_breakdown'],
                ];
            }

            // Server-side price lookup: never trust client-supplied transfer price.
            // Also enforces hotel scope — only transfer options that belong to this hotel.
            $transferOptions = [];
            foreach ($transfers as $idx => $transfer) {
                $option = HotelTransferOption::query()
                    ->where('hotel_id', $hotel->id)
                    ->where('id', $transfer['transfer_option_id'] ?? null)
                    ->where('is_active', true)
                    ->first();

                if (! $option) {
                    abort(422, "Transfer option #{$idx} is invalid or not available for this hotel.");
                }

                $transferOptions[$idx] = $option;
                $subtotalTransfer += (float) $option->price;
            }

            // Magic-link token is derived deterministically from the reservation
            // number (see magicLinkTokenFor) so later emails/jobs rebuild the
            // identical link instead of rolling it - rolling would invalidate the
            // token already embedded in the Xendit success_url. Only the hash is
            // stored, matching resolveByToken().
            $reservationNumber = $this->generateReservationNumber();
            $rawToken = $this->magicLinkTokenForNumber($reservationNumber);

            // Initial pricing snapshot - tax/service/total will be recomputed by PricingService
            // after items, transfers, penalties, and promo code adjustments are attached.
            $reservation = Reservation::create([
                'reservation_number' => $reservationNumber,
                'event_id' => $data['event_id'],
                'hotel_id' => $hotel->id,
                'status' => $data['status'] ?? ReservationStatus::PendingPayment,
                'payment_expires_at' => now()->addSeconds(config('xendit.invoice_duration', 86400)),
                'guest_name' => $data['guest_name'],
                'guest_email' => $data['guest_email'],
                'guest_phone' => $data['guest_phone'],
                'guest_identity_type' => $data['guest_identity_type'],
                'guest_identity_number' => $data['guest_identity_number'],
                'guest_nationality' => $data['guest_nationality'] ?? null,
                'guest_company' => $data['guest_company'] ?? null,
                'special_request' => $data['special_request'] ?? null,
                'subtotal_rooms' => $subtotalRooms,
                'subtotal_transfer' => $subtotalTransfer,
                'surcharge_amount' => $surchargeAmount,
                'penalty_amount' => 0,
                'tax_amount' => 0,
                'service_charge_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 0,
                'magic_link_token' => hash('sha256', $rawToken),
                // Generous window: the token is no longer rolled per email, so
                // this must outlast the whole booking lifecycle (booking far
                // ahead of the event + post-stay access to invoice/receipt).
                'magic_link_expires_at' => now()->addYears(2),
                'source' => $data['source'] ?? ReservationSource::PublicWebsite,
                'project_username' => $data['project_username'] ?? null,
                'ip_address' => $data['ip_address'] ?? null,
                'user_agent' => $data['user_agent'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($itemPayloads as $payload) {
                $reservation->items()->create($payload);
            }

            foreach ($transfers as $idx => $transfer) {
                $reservation->transfers()->create([
                    'transfer_option_id' => $transferOptions[$idx]->id,
                    'direction' => $transfer['direction'],
                    'transfer_date' => $transfer['transfer_date'],
                    'transfer_time' => $transfer['transfer_time'] ?? null,
                    'pickup_location' => $transfer['pickup_location'] ?? null,
                    'dropoff_location' => $transfer['dropoff_location'] ?? null,
                    'flight_number' => $transfer['flight_number'] ?? null,
                    'flight_time' => $transfer['flight_time'] ?? null,
                    'pax_count' => $transfer['pax_count'] ?? 1,
                    'luggage_count' => $transfer['luggage_count'] ?? null,
                    'note' => $transfer['note'] ?? null,
                    // Server-side price (not client). C1+C2 fix.
                    'price' => (float) $transferOptions[$idx]->price,
                ]);
            }

            // Evaluate auto-triggered penalty rules (booking_window, event_period, etc).
            $reservation = $reservation->fresh(['items', 'transfers', 'event', 'hotel']);
            $this->penalties->evaluateAndApply($reservation);

            // Apply promo code + finalize pricing. Both run as part of creating
            // the reservation, so they are wrapped in withoutLogs: the "created"
            // activity already records the reservation. Logging these would emit
            // misleading "updated total amount: Rp0 -> ..." entries at creation.
            // Throws ValidationException on invalid code, aborting the transaction.
            // Frontend should validate via POST /api/public/promo-codes/validate
            // BEFORE submitting.
            activity()->withoutLogs(function () use ($data, $reservation): void {
                if (! empty($data['promo_code'])) {
                    $this->promoCodes->applyByCode(
                        (string) $data['promo_code'],
                        $reservation->fresh(['items', 'transfers', 'adjustments.promotionRule']),
                        (string) ($data['guest_email'] ?? ''),
                        auth()->id(),
                    );
                    $reservation->forceFill([
                        'promo_code_applied' => strtoupper(trim((string) $data['promo_code'])),
                    ])->save();
                }

                // Final recalculate + persist with all adjustments applied.
                $this->pricing->recalculateAndPersist(
                    $reservation->fresh(['items', 'transfers', 'adjustments', 'hotel'])
                );
            });

            $reservation = $reservation->fresh(['items', 'transfers', 'adjustments', 'hotel', 'event']);
            $reservation->magicLinkRaw = $rawToken;

            return $reservation;
        });

        $rawToken = $reservation->magicLinkRaw;

        $totalAmount = (float) $reservation->total_amount;

        // Total <= 0 always forces complimentary regardless of payment_mode flags.
        // This handles 100% discount scenarios (skip Xendit, no payment URL needed).
        if ($totalAmount <= 0) {
            $reservation->update([
                'total_amount' => 0,
                'status' => ReservationStatus::Paid,
                'paid_at' => now(),
                'payment_method' => PaymentMethod::Complimentary,
                'payment_expires_at' => null,
            ]);
            SendBookingReceivedJob::dispatch($reservation->id);
        } elseif (($data['skip_payment'] ?? false) === true) {
            $reservation->update([
                'status' => ReservationStatus::Paid,
                'paid_at' => now(),
                'payment_method' => PaymentMethod::Complimentary,
                'payment_expires_at' => null,
            ]);
            SendBookingReceivedJob::dispatch($reservation->id);
        } elseif (($data['mark_paid_manual'] ?? false) === true) {
            $reservation->update([
                'status' => ReservationStatus::Paid,
                'paid_at' => now(),
                'payment_method' => PaymentMethod::ManualBankTransfer,
                'payment_expires_at' => null,
            ]);
            SendBookingReceivedJob::dispatch($reservation->id);
        } elseif (($data['generate_xendit'] ?? true) === true) {
            try {
                $xenditClient = $xendit ?? $this->xenditFor($reservation);
                $frontendUrl = rtrim(config('app.frontend_url'), '/');
                $successUrl = "{$frontendUrl}/hotels/success?ref={$reservation->reservation_number}&token={$rawToken}";
                $failureUrl = "{$frontendUrl}/hotels?failed=".$reservation->reservation_number;
                // createCheckout() dispatches to the Sessions API or the legacy
                // Invoices API depending on the gateway's checkout_method.
                // `xendit_invoice_id` therefore holds either an `inv-` invoice
                // id or a `ps-` Payment Session id.
                // `$data['origins']` is forwarded so a COMPONENTS-mode session
                // gets a usable `components_configuration.origins`.
                $checkout = $xenditClient->createCheckout(
                    $reservation,
                    $successUrl,
                    $failureUrl,
                    $data['origins'] ?? null,
                );
                $reservation->update([
                    'xendit_invoice_id' => $checkout['reference'],
                    'payment_url' => $checkout['payment_url'],
                    // Persist the Components SDK key so subsequent magic-link
                    // page loads can re-mount the same embedded checkout
                    // without re-creating the Xendit session (which the
                    // Sessions API rejects on duplicate reference_id).
                    'components_sdk_key' => $checkout['components_sdk_key'] ?? null,
                    'payment_method' => PaymentMethod::Xendit,
                    'payment_gateway_id' => $xenditClient->gateway()?->id,
                ]);
            } catch (\Throwable $e) {
                $mapped = XenditErrorMapper::map($e);
                Log::log($mapped['log_level'], 'Xendit invoice creation failed - reservation kept for retry', [
                    'reservation_id' => $reservation->id,
                    'reservation_number' => $reservation->reservation_number,
                    'error_code' => $mapped['error_code'],
                    'raw_error' => $e->getMessage(),
                ]);
            }

            // Intentionally no email dispatch here: pending_payment is a
            // transient state. The Xendit webhook (or the staff "Mark as
            // paid" action when the webhook is unavailable, e.g. local dev)
            // will call markAsPaid, which dispatches a single confirmation
            // email. Sending one at creation AND one at payment would
            // double-message the guest within minutes for the common
            // pay-right-away flow.
        }

        $fresh = $reservation->fresh(['items', 'transfers', 'hotel', 'event']);
        $fresh->magicLinkRaw = $rawToken;

        return $fresh;
    }

    /**
     * Regenerate Xendit invoice for a reservation that is still pending payment.
     *
     * Used when the initial invoice creation failed (network issue, gateway
     * misconfiguration) and the reservation was kept alive for retry. Re-uses
     * the existing magic_link_token so customer can still pay via their
     * original link.
     *
     * @throws HttpException
     */
    public function retryXenditInvoice(
        Reservation $reservation,
        ?XenditService $xendit = null,
        ?array $origins = null,
    ): Reservation {
        if ($reservation->status !== ReservationStatus::PendingPayment) {
            abort(422, 'Only pending payments can be retried.');
        }

        $xenditClient = $xendit ?? $this->xenditFor($reservation);

        if (! $xenditClient->gateway()) {
            abort(422, 'No active payment gateway is configured for this project.');
        }

        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $rawToken = $this->magicLinkTokenFor($reservation);
        $successUrl = "{$frontendUrl}/hotels/success?ref={$reservation->reservation_number}&token={$rawToken}";
        $failureUrl = "{$frontendUrl}/hotels?failed={$reservation->reservation_number}";

        $checkout = $xenditClient->createCheckout($reservation, $successUrl, $failureUrl, $origins);

        $reservation->update([
            'xendit_invoice_id' => $checkout['reference'],
            'payment_url' => $checkout['payment_url'],
            'components_sdk_key' => $checkout['components_sdk_key'] ?? null,
            'payment_method' => PaymentMethod::Xendit,
            'payment_gateway_id' => $xenditClient->gateway()?->id,
        ]);

        return $reservation->fresh();
    }

    /**
     * Mark reservation as paid using info from Xendit invoice payload.
     *
     * Accepts the full Xendit invoice.paid webhook payload to extract channel
     * details (BCA/OVO/QRIS, VA number, payment_id). When called with an empty
     * payload, behavior matches the legacy scalar-only call site.
     *
     * @param  array<string, mixed>  $payload  Full Xendit webhook body
     */
    public function markAsPaid(Reservation $reservation, array $payload = []): void
    {
        if ($reservation->status->isPaid()) {
            return;
        }

        $update = [
            'status' => ReservationStatus::Paid,
            'paid_at' => now(),
            'xendit_invoice_id' => $payload['id'] ?? $reservation->xendit_invoice_id,
            'payment_method' => $reservation->payment_method ?? PaymentMethod::Xendit,
            // SDK key is only useful while pending; clear on transition to
            // paid so it can't be served by the magic-link GET afterwards.
            'components_sdk_key' => null,
        ];

        if (! empty($payload['payment_channel'])) {
            $update['payment_channel'] = $payload['payment_channel'];
        } elseif (! empty($payload['bank_code'])) {
            // Fallback: legacy alias when payment_channel is absent.
            $update['payment_channel'] = $payload['bank_code'];
        }

        if (! empty($payload['payment_destination'])) {
            $update['payment_destination'] = $payload['payment_destination'];
        }

        if (! empty($payload['payment_id'])) {
            $update['xendit_payment_id'] = $payload['payment_id'];
        }

        // Xendit's simulator + some live channels omit `payment_channel` from
        // the webhook payload. Fetch the invoice detail directly so the
        // receipt can render the correct bank/wallet logo instead of falling
        // back to the generic "Xendit" string.
        $invoiceId = $payload['id'] ?? $reservation->xendit_invoice_id;
        $detail = null;
        if (empty($update['payment_channel']) && $invoiceId && $reservation->paymentGateway) {
            $detail = $this->xenditFor($reservation)->fetchInvoiceDetail($invoiceId);
            if ($detail) {
                if (! empty($detail['payment_channel'])) {
                    $update['payment_channel'] = $detail['payment_channel'];
                } elseif (! empty($detail['bank_code'])) {
                    $update['payment_channel'] = $detail['bank_code'];
                }
                if (empty($update['payment_destination']) && ! empty($detail['payment_destination'])) {
                    $update['payment_destination'] = $detail['payment_destination'];
                }
                if (! empty($detail['payment_id']) && empty($update['xendit_payment_id'])) {
                    $update['xendit_payment_id'] = $detail['payment_id'];
                }
            }
        }

        // Credit card payments all come back on the single CREDIT_CARD channel.
        // Resolve the actual network (Visa/Mastercard/Amex/JCB) from the card
        // charge so receipts and listings show the correct brand logo.
        if (($update['payment_channel'] ?? null) === 'CREDIT_CARD' && $invoiceId && $reservation->paymentGateway) {
            $chargeId = $payload['credit_card_charge_id'] ?? ($detail['credit_card_charge_id'] ?? null);
            if (! $chargeId) {
                $detail ??= $this->xenditFor($reservation)->fetchInvoiceDetail($invoiceId);
                $chargeId = $detail['credit_card_charge_id'] ?? null;
            }
            if ($chargeId) {
                $brand = $this->xenditFor($reservation)->fetchCardBrand((string) $chargeId);
                if ($brand) {
                    $update['payment_channel'] = $brand;
                }
            }
        }

        // Conditional update on PendingPayment status — guarantees only-once
        // pending → paid transition even when two webhooks arrive concurrently.
        // Only the first transaction that satisfies the WHERE clause updates and
        // dispatches the email job; the second is a no-op (C3 fix).
        $updated = Reservation::query()
            ->whereKey($reservation->id)
            ->where('status', ReservationStatus::PendingPayment->value)
            ->update($update);

        if ($updated > 0) {
            SendBookingReceivedJob::dispatch($reservation->id);
        }
    }

    public function expireReservation(Reservation $reservation): void
    {
        if ($reservation->status !== ReservationStatus::PendingPayment) {
            return;
        }

        // Revert promo usage counters and void adjustments before flipping status —
        // an unpaid expired reservation never consumed value, so the promo code
        // should be reusable. Mirrors cancel() behavior.
        $this->promoCodes->voidAllOnCancel($reservation);

        $reservation->update([
            'status' => ReservationStatus::Expired,
            'components_sdk_key' => null,
        ]);
    }

    /**
     * Resolve per-night rates for a room type across a date range.
     *
     * Precedence:
     *  1. Dynamic pricing period (when pricing_type=Dynamic and a covering period exists)
     *  2. Allotment base_rate_override (when flat-priced, allotment provided + override set)
     *  3. RoomType base_rate (default)
     *
     * Surcharge stacks on top via previewSubtotal().
     *
     * @return array{nights: int, daily: list<array{date: string, rate: float}>, subtotal_base: float}
     *
     * @throws ValidationException when pricing_type=dynamic and a night has no covering period
     */
    public function resolveNightlyRates(RoomType $roomType, Carbon $checkIn, Carbon $checkOut, ?HotelEventAllotment $allotment = null): array
    {
        $nights = (int) $checkIn->diffInDays($checkOut);

        if ($nights < 1) {
            throw new \InvalidArgumentException('Check-out must be after check-in.');
        }

        $isDynamic = $roomType->pricing_type === PricingType::Dynamic;
        $periods = $isDynamic
            ? $roomType->pricingPeriods()->active()->get()
            : null;

        $overrideBase = $allotment?->getEffectiveBaseRate();

        $daily = [];
        $subtotalBase = 0.0;

        for ($i = 0; $i < $nights; $i++) {
            $date = $checkIn->copy()->addDays($i);
            $dateString = $date->toDateString();

            if ($isDynamic) {
                $period = $periods->first(function ($p) use ($dateString) {
                    return $p->start_date->toDateString() <= $dateString
                        && $p->end_date->toDateString() >= $dateString;
                });

                if (! $period) {
                    throw ValidationException::withMessages([
                        'items' => "No pricing configured for {$roomType->name} on {$dateString}.",
                    ]);
                }

                $rate = (float) $period->rate;
            } else {
                $rate = $overrideBase ?? (float) $roomType->base_rate;
            }

            $daily[] = ['date' => $dateString, 'rate' => $rate];
            $subtotalBase += $rate;
        }

        return [
            'nights' => $nights,
            'daily' => $daily,
            'subtotal_base' => $subtotalBase,
        ];
    }

    /**
     * Compute room subtotal preview for a booking.
     *
     * Returns the same numbers used at booking time so availability quotes and
     * actual reservations cannot drift. Surcharge stacks on top of resolved nightly rates.
     *
     * @return array{subtotal: float, surcharge: float, rate_per_night_avg: float, daily_breakdown: list<array{date: string, rate: float}>, nights: int}
     *
     * @throws ValidationException when dynamic pricing has uncovered nights
     */
    public function previewSubtotal(
        RoomType $roomType,
        Carbon $checkIn,
        Carbon $checkOut,
        int $qty,
        ?HotelEventAllotment $allotment = null,
    ): array {
        $rates = $this->resolveNightlyRates($roomType, $checkIn, $checkOut, $allotment);
        $nights = $rates['nights'];
        $daily = $rates['daily'];
        $subtotalBase = $rates['subtotal_base'];

        $surchargePerRoom = 0.0;
        if ($allotment && $allotment->surcharge_type === 'fixed') {
            $surchargePerRoom = (float) $allotment->surcharge_amount * $nights;
        } elseif ($allotment && $allotment->surcharge_type === 'percentage') {
            $pct = (float) $allotment->surcharge_amount / 100;
            foreach ($daily as $d) {
                $surchargePerRoom += $d['rate'] * $pct;
            }
        }

        $subtotal = ($subtotalBase + $surchargePerRoom) * $qty;
        $surcharge = $surchargePerRoom * $qty;
        $ratePerNightAvg = ($subtotalBase + $surchargePerRoom) / $nights;

        return [
            'subtotal' => $subtotal,
            'surcharge' => $surcharge,
            'rate_per_night_avg' => $ratePerNightAvg,
            'daily_breakdown' => $daily,
            'nights' => $nights,
        ];
    }

    /**
     * Per-date breakdown of rate + remaining allotment for a room type.
     *
     * Returns one entry per day from $start to $end (inclusive). Rate mirrors
     * resolveNightlyRates() (flat=base_rate, dynamic=covering period) plus any
     * surcharge from the covering allotment. Available mirrors checkAvailability()
     * (sum of allotments - committed reservation items overlapping the date).
     *
     * Three queries total (allotments, pricingPeriods, reservation items) — safe for ranges up to ~92 days.
     *
     * @return array<int, array{date: string, rate: float|null, available: int}>
     */
    public function dailyAvailability(Hotel $hotel, RoomType $roomType, Carbon $start, Carbon $end): array
    {
        $startIso = $start->toDateString();
        $endIso = $end->toDateString();

        $allotments = HotelEventAllotment::query()
            ->active()
            ->where('hotel_id', $hotel->id)
            ->where('room_type_id', $roomType->id)
            ->whereDate('end_date', '>=', $startIso)
            ->whereDate('start_date', '<=', $endIso)
            ->get();

        $isDynamic = $roomType->pricing_type === PricingType::Dynamic;
        $periods = $isDynamic
            ? $roomType->pricingPeriods()->active()->get()
            : collect();

        $allowUnlimited = (bool) ($hotel->settings['allow_unlimited_booking'] ?? false);

        // Reservation items that overlap any day in the range. Use Carbon end+1 for inclusive overlap.
        $items = ReservationItem::query()
            ->whereHas('reservation', function ($q) use ($hotel) {
                $q->where('hotel_id', $hotel->id)
                    ->where(function ($inner) {
                        $inner->whereIn('status', [
                            ReservationStatus::Paid->value,
                            ReservationStatus::VoucherSent->value,
                        ])->orWhere(function ($pending) {
                            $pending->where('status', ReservationStatus::PendingPayment->value)
                                ->where('payment_expires_at', '>', now());
                        });
                    });
            })
            ->where('room_type_id', $roomType->id)
            ->whereDate('check_in_date', '<=', $endIso)
            ->whereDate('check_out_date', '>', $startIso)
            ->get(['check_in_date', 'check_out_date', 'qty']);

        $totalDays = (int) $start->diffInDays($end) + 1;
        $out = [];

        for ($i = 0; $i < $totalDays; $i++) {
            $date = $start->copy()->addDays($i);
            $iso = $date->toDateString();

            $covering = $allotments->filter(fn ($a) => $a->start_date->toDateString() <= $iso
                && $a->end_date->toDateString() >= $iso
            );
            $coveringAllot = $covering->first();

            $rate = null;
            if ($isDynamic) {
                $period = $periods->first(fn ($p) => $p->start_date->toDateString() <= $iso
                    && $p->end_date->toDateString() >= $iso
                );
                $rate = $period ? (float) $period->rate : null;
            } else {
                // Precedence: allotment override > room base_rate
                $rate = $coveringAllot?->getEffectiveBaseRate() ?? (float) $roomType->base_rate;
            }

            if ($covering->isEmpty()) {
                $available = $allowUnlimited ? 999 : 0;
            } else {
                $total = (int) $covering->sum('quantity');

                $committed = (int) $items
                    ->filter(fn ($it) => $it->check_in_date->toDateString() <= $iso
                        && $it->check_out_date->toDateString() > $iso
                    )
                    ->sum('qty');

                $available = max(0, $total - $committed);

                if ($rate !== null && $coveringAllot) {
                    if ($coveringAllot->surcharge_type === 'fixed') {
                        $rate += (float) $coveringAllot->surcharge_amount;
                    } elseif ($coveringAllot->surcharge_type === 'percentage') {
                        $rate *= (1 + ((float) $coveringAllot->surcharge_amount / 100));
                    }
                }
            }

            if ($rate === null) {
                $available = 0;
            }

            $out[] = [
                'date' => $iso,
                'rate' => $rate !== null ? round($rate, 2) : null,
                'available' => $available,
            ];
        }

        return $out;
    }

    /**
     * Aggregate min/max rate + total availability across all active room types of a hotel.
     *
     * Per date returns: min_rate (cheapest available room), max_rate, total_available
     * (sum of remaining qty across rooms), rooms_count (rooms with rate + available > 0).
     *
     * @return array<int, array{date: string, min_rate: float|null, max_rate: float|null, total_available: int, rooms_count: int}>
     */
    public function aggregateDailyAvailability(Hotel $hotel, Carbon $start, Carbon $end): array
    {
        $rooms = $hotel->roomTypes()->active()->with('pricingPeriods')->get();

        if ($rooms->isEmpty()) {
            return [];
        }

        $perRoom = [];
        foreach ($rooms as $room) {
            $perRoom[] = $this->dailyAvailability($hotel, $room, $start, $end);
        }

        $totalDays = (int) $start->diffInDays($end) + 1;
        $out = [];

        for ($i = 0; $i < $totalDays; $i++) {
            $iso = $start->copy()->addDays($i)->toDateString();

            $rates = [];
            $totalAvailable = 0;
            $roomsCount = 0;

            foreach ($perRoom as $roomDays) {
                $cell = $roomDays[$i] ?? null;
                if (! $cell) {
                    continue;
                }
                if ($cell['rate'] !== null && $cell['available'] > 0) {
                    $rates[] = (float) $cell['rate'];
                    $totalAvailable += (int) $cell['available'];
                    $roomsCount++;
                }
            }

            $out[] = [
                'date' => $iso,
                'min_rate' => $rates ? min($rates) : null,
                'max_rate' => $rates ? max($rates) : null,
                'total_available' => $totalAvailable,
                'rooms_count' => $roomsCount,
            ];
        }

        return $out;
    }

    public function calculateRefund(Reservation $reservation): float
    {
        $earliestCheckIn = $reservation->items()
            ->orderBy('check_in_date')
            ->value('check_in_date');

        if (! $earliestCheckIn) {
            return 0.0;
        }

        $timezone = config('app.timezone', 'Asia/Jakarta');
        $today = Carbon::now($timezone)->startOfDay();
        $checkInDay = Carbon::parse($earliestCheckIn, $timezone)->startOfDay();
        $daysUntil = (int) $today->diffInDays($checkInDay, false);
        $total = (float) $reservation->total_amount;

        if ($daysUntil >= 14) {
            return round($total, 2);
        }

        if ($daysUntil >= 7) {
            return round($total * 0.5, 2);
        }

        return 0.0;
    }

    public function generateReservationNumber(): string
    {
        $prefix = 'HTL-'.now()->format('Ymd').'-';

        do {
            $candidate = $prefix.strtoupper(Str::random(4));
        } while (Reservation::query()->where('reservation_number', $candidate)->exists());

        return $candidate;
    }

    /**
     * Deterministic magic-link token for a reservation.
     *
     * Derived via HMAC from the reservation's ULID and the app key, so any
     * later process (payment webhook, voucher/cancellation jobs) can rebuild
     * the identical token without rolling it. Only the SHA-256 hash is stored
     * in `magic_link_token`; `resolveByToken()` hashes the incoming token the
     * same way, so legacy random tokens keep resolving too.
     */
    public function magicLinkTokenFor(Reservation $reservation): string
    {
        return $this->magicLinkTokenForNumber($reservation->reservation_number);
    }

    private function magicLinkTokenForNumber(string $reservationNumber): string
    {
        return hash_hmac('sha256', 'hotel-reservation-magic:'.$reservationNumber, config('app.key'));
    }
}
