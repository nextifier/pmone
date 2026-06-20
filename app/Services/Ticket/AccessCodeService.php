<?php

namespace App\Services\Ticket;

use App\DTOs\Ticketing\AccessCodeValidation;
use App\Enums\AdjustmentKind;
use App\Enums\AdjustmentValueType;
use App\Enums\Ticketing\AccessCodeKind;
use App\Enums\Ticketing\AccessCodePriceEffect;
use App\Enums\Ticketing\AccessCodeStatus;
use App\Models\AccessCode;
use App\Models\AccessCodeBatch;
use App\Models\AccessCodeRedemption;
use App\Models\AppliedAdjustment;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Access code lifecycle: validation (eligibility), atomic hold/consume/release
 * (mirrors PromoCodeService' race-safe counter), optional price effect written
 * as a frozen FixedAmount AppliedAdjustment, and batch generation.
 *
 * Pricing is reused from the existing engine: a FixedAmount adjustment with
 * promotion_rule_id = null is fully null-safe in PricingService, so the access
 * code's price effect needs no PromotionRule.
 */
class AccessCodeService
{
    /**
     * Validate a code against an event + buyer + cart, without applying it.
     *
     * @param  array<int, array{ticket_id:int, quantity?:int}>  $cartItems
     */
    public function validate(
        string $code,
        Event $event,
        ?string $email = null,
        ?string $phone = null,
        array $cartItems = [],
        bool $hasPromo = false,
    ): AccessCodeValidation {
        $normalized = strtoupper(trim($code));

        if ($normalized === '') {
            return AccessCodeValidation::fail(
                AccessCodeValidation::ERROR_INVALID_CODE,
                'Code is required.',
            );
        }

        /** @var AccessCode|null $accessCode */
        $accessCode = AccessCode::query()
            ->with('unlocks:id,slug,title')
            ->where('code', $normalized)
            ->where('event_id', $event->id)
            ->first();

        if (! $accessCode) {
            return AccessCodeValidation::fail(
                AccessCodeValidation::ERROR_INVALID_CODE,
                'Access code not found.',
            );
        }

        if ($accessCode->status === AccessCodeStatus::Revoked) {
            return AccessCodeValidation::fail(
                AccessCodeValidation::ERROR_REVOKED,
                'This access code has been revoked.',
                $accessCode,
            );
        }

        $now = now();
        if ($accessCode->valid_from && $now->lessThan($accessCode->valid_from)) {
            return AccessCodeValidation::fail(
                AccessCodeValidation::ERROR_NOT_YET_VALID,
                'This access code is not yet valid.',
                $accessCode,
            );
        }

        if ($accessCode->valid_until && $now->greaterThan($accessCode->valid_until)) {
            return AccessCodeValidation::fail(
                AccessCodeValidation::ERROR_EXPIRED,
                'This access code has expired.',
                $accessCode,
            );
        }

        if ($accessCode->isFullyUsed()) {
            return AccessCodeValidation::fail(
                AccessCodeValidation::ERROR_USAGE_LIMIT_REACHED,
                'This access code has reached its usage limit.',
                $accessCode,
            );
        }

        if ($accessCode->bind_email && strtolower(trim((string) $email)) !== $accessCode->bind_email) {
            return AccessCodeValidation::fail(
                AccessCodeValidation::ERROR_BIND_EMAIL_MISMATCH,
                'This access code is tied to a different email address.',
                $accessCode,
            );
        }

        if ($accessCode->bind_phone && $this->normalizePhone($phone) !== $this->normalizePhone($accessCode->bind_phone)) {
            return AccessCodeValidation::fail(
                AccessCodeValidation::ERROR_BIND_PHONE_MISMATCH,
                'This access code is tied to a different phone number.',
                $accessCode,
            );
        }

        // Stacking: a price-affecting, non-stackable code cannot ride along a promo.
        if ($hasPromo && $accessCode->price_effect->affectsPrice() && ! $accessCode->stackable) {
            return AccessCodeValidation::fail(
                AccessCodeValidation::ERROR_STACKING_NOT_ALLOWED,
                'This access code cannot be combined with a promo code.',
                $accessCode,
            );
        }

        // Every gated ticket in the cart must be unlocked by this code, and the
        // requested quantity must respect max_qty_per_redemption.
        if (! empty($cartItems)) {
            $ticketIds = collect($cartItems)->pluck('ticket_id')->filter()->map(fn ($v) => (int) $v)->all();
            $gated = Ticket::query()
                ->whereIn('id', $ticketIds)
                ->where('event_id', $event->id)
                ->get(['id', 'visibility'])
                ->keyBy('id');

            foreach ($cartItems as $item) {
                $ticketId = (int) ($item['ticket_id'] ?? 0);
                $qty = max(1, (int) ($item['quantity'] ?? 1));
                $ticket = $gated->get($ticketId);

                if ($ticket && $ticket->isGated() && ! $accessCode->unlocksTicket($ticketId)) {
                    return AccessCodeValidation::fail(
                        AccessCodeValidation::ERROR_TICKET_NOT_UNLOCKED,
                        'This access code does not unlock one of the selected tickets.',
                        $accessCode,
                    );
                }

                if ($accessCode->unlocksTicket($ticketId) && $qty > $accessCode->max_qty_per_redemption) {
                    return AccessCodeValidation::fail(
                        AccessCodeValidation::ERROR_QTY_EXCEEDS_REDEMPTION_LIMIT,
                        "This access code allows up to {$accessCode->max_qty_per_redemption} ticket(s) per redemption.",
                        $accessCode,
                    );
                }
            }
        }

        $preview = null;
        if ($accessCode->price_effect->affectsPrice() && ! empty($cartItems)) {
            $preview = $this->computePriceEffectDiscount($accessCode, $cartItems)['amount'];
        }

        return AccessCodeValidation::ok(
            $accessCode,
            $accessCode->unlocks->map(fn (Ticket $t) => [
                'ticket_id' => $t->id,
                'slug' => $t->slug,
                'title' => $t->getTranslation('title', app()->getLocale(), false),
            ])->all(),
            $preview,
        );
    }

    /**
     * Compute the price-effect discount, scoped only to the unlocked tickets
     * present in the cart. Pure (no side effects). Lines may be order items or
     * normalized arrays {ticket_id, unit_price, quantity}.
     *
     * @param  iterable<int, mixed>  $lines
     * @return array{amount: float, breakdown: array<string, mixed>}
     */
    public function computePriceEffectDiscount(AccessCode $accessCode, iterable $lines): array
    {
        $unlockedIds = $accessCode->relationLoaded('unlocks')
            ? $accessCode->unlocks->pluck('id')->all()
            : $accessCode->unlocks()->pluck('tickets.id')->all();

        $effect = $accessCode->price_effect;
        $value = (float) ($accessCode->price_value ?? 0);

        $perLine = [];
        $unlockedSubtotal = 0.0;
        $discount = 0.0;

        foreach ($lines as $line) {
            [$ticketId, $unitPrice, $qty] = $this->normalizeLine($line);

            if (! in_array($ticketId, $unlockedIds, true)) {
                continue;
            }

            $lineSubtotal = $unitPrice * $qty;
            $unlockedSubtotal += $lineSubtotal;

            $lineDiscount = match ($effect) {
                AccessCodePriceEffect::SetPrice => max(0.0, $unitPrice - $value) * $qty,
                AccessCodePriceEffect::Percentage => $lineSubtotal * $value / 100,
                default => 0.0,
            };

            $discount += $lineDiscount;
            $perLine[] = [
                'ticket_id' => $ticketId,
                'unit_price' => round($unitPrice, 2),
                'quantity' => $qty,
                'discount' => round($lineDiscount, 2),
            ];
        }

        // "amount" is a flat per-redemption discount, clamped to the unlocked subtotal.
        if ($effect === AccessCodePriceEffect::Amount) {
            $discount = min($value, $unlockedSubtotal);
        }

        $discount = round(min($discount, $unlockedSubtotal), 2);

        return [
            'amount' => $discount,
            'breakdown' => [
                'price_effect' => $effect->value,
                'price_value' => $value,
                'unlocked_ticket_ids' => array_values($unlockedIds),
                'unlocked_subtotal' => round($unlockedSubtotal, 2),
                'per_line' => $perLine,
            ],
        ];
    }

    /**
     * Hold the code for an order: atomic used_count increment, redemption row,
     * and (when price_effect != none) a frozen FixedAmount adjustment scoped to
     * the unlocked lines. The caller recalculates totals afterwards.
     *
     * Returns the created adjustment, or null for a pure-gate (price_effect=none)
     * code.
     */
    public function apply(AccessCode $accessCode, TicketOrder $order, ?string $email = null, ?int $userId = null): ?AppliedAdjustment
    {
        return DB::transaction(function () use ($accessCode, $order, $email, $userId) {
            /** @var AccessCode $locked */
            $locked = AccessCode::query()
                ->with('unlocks:id,slug,title')
                ->whereKey($accessCode->id)
                ->lockForUpdate()
                ->firstOrFail();

            $order->loadMissing('items');
            $cartItems = $order->items->map(fn ($item) => [
                'ticket_id' => (int) $item->ticket_id,
                'quantity' => (int) $item->quantity,
            ])->all();

            // Re-validate inside the lock (status/window/limit/bind/unlock) — but
            // not stacking, which the caller has already enforced.
            $validation = $this->validate(
                $locked->code,
                $order->event ?? $locked->event,
                $email ?? $order->buyer_email,
                $order->buyer_phone,
                $cartItems,
                hasPromo: false,
            );

            if (! $validation->valid) {
                throw ValidationException::withMessages([
                    'access_code' => [($validation->errorCode ?? 'INVALID').': '.($validation->message ?? 'Invalid access code')],
                ]);
            }

            // Atomic counter guard (omit predicate for unlimited shared codes).
            $affected = AccessCode::query()
                ->whereKey($locked->id)
                ->when($locked->max_uses !== null, fn ($q) => $q->whereRaw('used_count < ?', [$locked->max_uses]))
                ->update(['used_count' => DB::raw('used_count + 1')]);

            if ($affected === 0) {
                throw ValidationException::withMessages([
                    'access_code' => [AccessCodeValidation::ERROR_USAGE_LIMIT_REACHED.': Access code usage limit reached.'],
                ]);
            }

            $redemption = AccessCodeRedemption::query()->create([
                'access_code_id' => $locked->id,
                'ticket_order_id' => $order->id,
                'email' => $email ?? $order->buyer_email,
                'redeemed_at' => null,
            ]);

            $adjustment = null;

            if ($locked->price_effect->affectsPrice()) {
                // Use the order items (they carry unit_price) for the scoped math.
                $computed = $this->computePriceEffectDiscount($locked, $order->items);

                if ($computed['amount'] > 0) {
                    $adjustment = AppliedAdjustment::query()->create([
                        'adjustable_type' => $order->getMorphClass(),
                        'adjustable_id' => $order->getKey(),
                        'promotion_rule_id' => null,
                        'promo_code_id' => null,
                        'access_code_id' => $locked->id,
                        'kind' => AdjustmentKind::Discount->value,
                        'label' => 'Access code '.$locked->code,
                        'value_type' => AdjustmentValueType::FixedAmount->value,
                        'value' => $computed['amount'],
                        'value_config' => null,
                        'base_amount' => 0,
                        'amount' => 0,
                        'rule_snapshot' => array_merge($computed['breakdown'], [
                            'access_code_id' => $locked->id,
                            'access_code_ulid' => $locked->ulid,
                            'code' => $locked->code,
                            'kind' => $locked->kind->value,
                            'snapshot_at' => now()->toIso8601String(),
                        ]),
                        'applied_by' => $userId ? "admin:{$userId}" : 'customer',
                    ]);

                    $redemption->update(['applied_adjustment_id' => $adjustment->id]);
                }
            }

            return $adjustment;
        });
    }

    /**
     * Stamp redeemed_at on the order's held redemptions (order confirmed). Audit
     * only — used_count was already incremented at hold time.
     */
    public function consume(TicketOrder $order): void
    {
        AccessCodeRedemption::query()
            ->where('ticket_order_id', $order->id)
            ->whereNull('voided_at')
            ->whereNull('redeemed_at')
            ->update(['redeemed_at' => now()]);
    }

    /**
     * Release the hold for an order (expired/cancelled): decrement used_count,
     * void the redemption + its adjustment. Idempotent (skips voided rows).
     */
    public function release(TicketOrder $order): void
    {
        DB::transaction(function () use ($order) {
            $redemptions = AccessCodeRedemption::query()
                ->where('ticket_order_id', $order->id)
                ->whereNull('voided_at')
                ->get();

            foreach ($redemptions as $redemption) {
                AccessCode::query()
                    ->whereKey($redemption->access_code_id)
                    ->where('used_count', '>', 0)
                    ->update(['used_count' => DB::raw('used_count - 1')]);

                if ($redemption->applied_adjustment_id) {
                    AppliedAdjustment::query()
                        ->whereKey($redemption->applied_adjustment_id)
                        ->whereNull('voided_at')
                        ->update(['voided_at' => now(), 'void_reason' => 'order_released']);
                }

                $redemption->update(['voided_at' => now()]);
            }
        });
    }

    /**
     * Generate a batch of codes + attach the unlocked tickets.
     *
     * @param  array<string, mixed>  $spec
     */
    public function generateBatch(Event $event, array $spec): AccessCodeBatch
    {
        $kind = $spec['kind'] instanceof AccessCodeKind
            ? $spec['kind']
            : AccessCodeKind::from((string) $spec['kind']);

        return DB::transaction(function () use ($event, $spec, $kind) {
            $batch = AccessCodeBatch::query()->create([
                'event_id' => $event->id,
                'name' => $spec['name'] ?? 'Access code batch',
                'kind' => $kind->value,
                'assigned_to' => $spec['assigned_to'] ?? null,
                'brand_id' => $spec['brand_id'] ?? null,
                'notes' => $spec['notes'] ?? null,
            ]);

            $unlocks = collect($spec['unlocks'] ?? [])->map(fn ($v) => (int) $v)->filter()->all();
            $length = (int) ($spec['length'] ?? 10);
            $prefix = (string) ($spec['prefix'] ?? '');

            $shared = [
                'valid_from' => $spec['valid_from'] ?? null,
                'valid_until' => $spec['valid_until'] ?? null,
                'price_effect' => $spec['price_effect'] ?? AccessCodePriceEffect::None->value,
                'price_value' => $spec['price_value'] ?? null,
                'stackable' => (bool) ($spec['stackable'] ?? false),
                'max_qty_per_redemption' => (int) ($spec['max_qty_per_redemption'] ?? 1),
            ];

            if ($kind === AccessCodeKind::Shared) {
                $code = $this->createCode($event, $batch, $kind, array_merge($shared, [
                    'code' => $this->generateUniqueCode($length, $prefix),
                    'max_uses' => $spec['max_uses'] ?? null,
                ]), $unlocks);
            } else {
                $recipients = $spec['recipients'] ?? [];
                $quantity = ! empty($recipients) ? count($recipients) : (int) ($spec['quantity'] ?? 0);

                for ($i = 0; $i < $quantity; $i++) {
                    $recipient = $recipients[$i] ?? [];
                    $this->createCode($event, $batch, $kind, array_merge($shared, [
                        'code' => $this->generateUniqueCode($length, $prefix),
                        'max_uses' => 1,
                        'bind_email' => $recipient['email'] ?? null,
                        'bind_phone' => $recipient['phone'] ?? null,
                    ]), $unlocks);
                }
            }

            return $batch->load('accessCodes.unlocks');
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int>  $unlocks
     */
    protected function createCode(Event $event, AccessCodeBatch $batch, AccessCodeKind $kind, array $attributes, array $unlocks): AccessCode
    {
        $code = AccessCode::query()->create(array_merge([
            'event_id' => $event->id,
            'batch_id' => $batch->id,
            'kind' => $kind->value,
            'status' => AccessCodeStatus::Active->value,
        ], $attributes));

        if (! empty($unlocks)) {
            $code->unlocks()->sync($unlocks);
        }

        return $code;
    }

    /**
     * Generate a unique random code (ambiguous chars stripped).
     */
    public function generateUniqueCode(int $length = 10, string $prefix = ''): string
    {
        if ($length < 4 || $length > 60) {
            throw new \InvalidArgumentException('Code length must be between 4 and 60.');
        }

        $maxAttempts = 20;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $random = strtoupper(Str::random($length));
            $random = strtr($random, ['0' => 'A', 'O' => 'B', '1' => 'C', 'I' => 'D', 'L' => 'E']);

            $code = strtoupper(trim($prefix.$random));

            if (! AccessCode::query()->where('code', $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Failed to generate unique access code after '.$maxAttempts.' attempts.');
    }

    /**
     * Revoke a code (forward-blocking; in-flight held/paid orders keep tickets).
     */
    public function revoke(AccessCode $accessCode, string $reason = ''): void
    {
        $metadata = $accessCode->metadata ?? [];
        $metadata['revoked_reason'] = $reason;
        $metadata['revoked_at'] = now()->toIso8601String();

        $accessCode->update([
            'status' => AccessCodeStatus::Revoked->value,
            'metadata' => $metadata,
        ]);
    }

    /**
     * @return array{0:int,1:float,2:int} [ticket_id, unit_price, quantity]
     */
    protected function normalizeLine(mixed $line): array
    {
        if (is_array($line)) {
            return [
                (int) ($line['ticket_id'] ?? 0),
                (float) ($line['unit_price'] ?? 0),
                max(1, (int) ($line['quantity'] ?? 1)),
            ];
        }

        // TicketOrderItem-like object
        return [
            (int) $line->ticket_id,
            (float) $line->unit_price,
            max(1, (int) $line->quantity),
        ];
    }

    protected function normalizePhone(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone) ?? '';
    }

    /**
     * Resolve the soft window start (alias kept for parity with PromoCode).
     */
    public function resolveWindowStart(AccessCode $accessCode): ?Carbon
    {
        return $accessCode->valid_from;
    }

    /**
     * Collect lines from a transient cart preview for price computation.
     *
     * @param  array<int, array<string, mixed>>  $previewLines
     * @return Collection<int, array{ticket_id:int, unit_price:float, quantity:int}>
     */
    public function linesFromPreview(array $previewLines): Collection
    {
        return collect($previewLines)->map(fn (array $line) => [
            'ticket_id' => (int) ($line['ticket_id'] ?? 0),
            'unit_price' => (float) ($line['unit_price'] ?? 0),
            'quantity' => max(1, (int) ($line['quantity'] ?? 1)),
        ]);
    }
}
