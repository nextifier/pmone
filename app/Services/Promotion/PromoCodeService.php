<?php

namespace App\Services\Promotion;

use App\Contracts\Pricing\Purchasable;
use App\DTOs\Promotion\PromoCodeValidation;
use App\Enums\AdjustmentValueType;
use App\Models\AppliedAdjustment;
use App\Models\PromoCode;
use App\Models\PromoCodeUsage;
use App\Models\PromotionRule;
use App\Models\Reservation;
use App\Services\Pricing\PricingService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Handles promo code lifecycle: validation, atomic apply, void, generation.
 *
 * Concurrency strategy: lockForUpdate() on promo_codes row + atomic
 * "UPDATE WHERE usage_count < usage_limit" with affected-row check.
 */
class PromoCodeService
{
    public function __construct(
        private readonly ApplicabilityChecker $applicability,
        private readonly StackingResolver $stacking,
        private readonly PricingService $pricing,
    ) {}

    /**
     * Validate a promo code without applying it.
     */
    public function validate(
        string $code,
        Purchasable $entity,
        string $email,
        ?int $userId = null,
    ): PromoCodeValidation {
        $normalized = strtoupper(trim($code));

        if ($normalized === '') {
            return PromoCodeValidation::fail(
                PromoCodeValidation::ERROR_INVALID_CODE,
                'Code is required.'
            );
        }

        $promo = PromoCode::query()
            ->with('promotionRule')
            ->where('code', $normalized)
            ->first();

        if (! $promo || ! $promo->promotionRule) {
            return PromoCodeValidation::fail(
                PromoCodeValidation::ERROR_INVALID_CODE,
                'Promo code not found.'
            );
        }

        $rule = $promo->promotionRule;

        if (! $promo->is_active || ! $rule->is_active) {
            return PromoCodeValidation::fail(
                PromoCodeValidation::ERROR_INACTIVE,
                'Promo code is inactive.',
                $promo,
                $rule,
            );
        }

        $now = now();
        $start = $promo->resolveWindowStart();
        $end = $promo->resolveWindowEnd();

        if ($start && $now->lessThan($start)) {
            return PromoCodeValidation::fail(
                PromoCodeValidation::ERROR_NOT_YET_VALID,
                'Promo code is not yet valid.',
                $promo,
                $rule,
            );
        }

        if ($end && $now->greaterThan($end)) {
            return PromoCodeValidation::fail(
                PromoCodeValidation::ERROR_EXPIRED,
                'Promo code has expired.',
                $promo,
                $rule,
            );
        }

        if ($promo->usage_limit !== null && $promo->usage_count >= $promo->usage_limit) {
            return PromoCodeValidation::fail(
                PromoCodeValidation::ERROR_USAGE_LIMIT_REACHED,
                'Promo code has reached its usage limit.',
                $promo,
                $rule,
            );
        }

        if ($promo->usage_limit_per_email !== null && $promo->usage_limit_per_email > 0) {
            $usedByEmail = PromoCodeUsage::query()
                ->where('promo_code_id', $promo->id)
                ->where('email', strtolower(trim($email)))
                ->whereNull('voided_at')
                ->count();

            if ($usedByEmail >= $promo->usage_limit_per_email) {
                return PromoCodeValidation::fail(
                    PromoCodeValidation::ERROR_ALREADY_USED,
                    'You have already used this promo code.',
                    $promo,
                    $rule,
                );
            }
        }

        if ($promo->issued_to_email && strtolower(trim($email)) !== $promo->issued_to_email) {
            return PromoCodeValidation::fail(
                PromoCodeValidation::ERROR_NOT_ELIGIBLE,
                'This promo code is not for your account.',
                $promo,
                $rule,
            );
        }

        if (! $rule->appliesToType($entity->getMorphClass())) {
            return PromoCodeValidation::fail(
                PromoCodeValidation::ERROR_NOT_APPLICABLE_TO_PURCHASE_TYPE,
                'This promo code does not apply to this purchase type.',
                $promo,
                $rule,
            );
        }

        $applicabilityResult = $this->applicability->run(
            $rule->applicability ?? [],
            $entity,
            $email,
            $userId,
        );

        if (! $applicabilityResult->passes) {
            return PromoCodeValidation::fail(
                PromoCodeValidation::ERROR_DOES_NOT_APPLY,
                'Promo code does not apply to this booking. Reason: '.$applicabilityResult->reason,
                $promo,
                $rule,
            );
        }

        $subtotal = $entity->subtotalForDiscountBase();
        if ($rule->min_purchase_amount !== null && $subtotal < (float) $rule->min_purchase_amount) {
            return PromoCodeValidation::fail(
                PromoCodeValidation::ERROR_MIN_PURCHASE_NOT_MET,
                'Minimum purchase amount not met.',
                $promo,
                $rule,
            );
        }

        $existing = $entity->adjustments()->with('promotionRule')->whereNull('voided_at')->get();
        if (! $this->stacking->canStack($rule, $existing)) {
            return PromoCodeValidation::fail(
                PromoCodeValidation::ERROR_STACKING_NOT_ALLOWED,
                'Promo code cannot be combined with current active promotions.',
                $promo,
                $rule,
            );
        }

        // Compute preview
        $preview = $this->pricing->preview($entity, $rule);

        // For buy_x_get_y, surface the bonus allocation so the cart UI can
        // explain "+ N free items" instead of a misleading -Rp 0 discount line.
        // Bonus items are only applied at final reservation create; the preview
        // discount stays 0 until then (total = pre-promo total).
        $bonusItems = null;
        if ($rule->value_type === AdjustmentValueType::BuyXGetY && $entity instanceof Reservation) {
            $bonusItems = $this->previewBuyXGetYBonus($entity, $rule);
        }

        // Reject conditional value types when the current cart can't benefit
        // (e.g. qty below buy_qty for BOGO, no tier matched, no eligible item).
        // Prevents the code from being consumed at apply() with a zero discount.
        $conditionalTypes = [
            AdjustmentValueType::BuyXGetY,
            AdjustmentValueType::BundlePrice,
            AdjustmentValueType::TieredPercentage,
            AdjustmentValueType::TieredFixedAmount,
            AdjustmentValueType::FreeAddon,
        ];

        if ($preview->discountAmount <= 0 && in_array($rule->value_type, $conditionalTypes, true)) {
            if ($rule->value_type === AdjustmentValueType::BuyXGetY && ! empty($bonusItems)) {
                // Bonus exists but final discount is 0 here because preview can't
                // mutate qty — allow the code through; apply() will allocate the bonus.
            } else {
                return PromoCodeValidation::fail(
                    PromoCodeValidation::ERROR_DOES_NOT_APPLY,
                    'This promo cannot be applied to your current cart. Check the minimum quantity or eligible items.',
                    $promo,
                    $rule,
                );
            }
        }

        return PromoCodeValidation::ok(
            rule: $rule,
            code: $promo,
            previewDiscount: $preview->discountAmount,
            previewTotal: $preview->totalAmount,
            bonusItems: $bonusItems,
        );
    }

    /**
     * Compute the bonus items a buy-x-get-y promo would allocate, without
     * mutating the reservation. Used to populate the validate-response preview
     * so the cart UI can show "+ 1 free Superior Room" before the customer commits.
     *
     * Respects applicability.room_types so non-eligible items don't get a free bonus.
     *
     * @return array<int, array{item_id: int|null, label: string, bonus_qty: int, unit_price: float}>
     */
    private function previewBuyXGetYBonus(Reservation $reservation, PromotionRule $rule): array
    {
        $config = $rule->value_config ?? [];
        $buyQty = max(1, (int) ($config['buy_qty'] ?? 1));
        $freeQty = max(1, (int) ($config['get_free_qty'] ?? 1));

        $items = $reservation->relationLoaded('items')
            ? $reservation->items
            : $reservation->items()->with('roomType')->get();

        $eligibleRoomTypes = $this->extractRoomTypeWhitelist($rule);

        $bonus = [];
        foreach ($items as $item) {
            if ($eligibleRoomTypes !== null && ! in_array((int) $item->room_type_id, $eligibleRoomTypes, true)) {
                continue;
            }

            $originalQty = (int) $item->qty;
            $passes = intdiv($originalQty, $buyQty);
            $bonusQty = $passes * $freeQty;

            if ($bonusQty <= 0) {
                continue;
            }

            $bonus[] = [
                'item_id' => $item->id,
                'label' => $item->roomType?->name ?? 'Room',
                'bonus_qty' => $bonusQty,
                'unit_price' => (float) $item->rate_per_night,
            ];
        }

        return $bonus;
    }

    /**
     * @return array<int, int>|null list of room_type_ids, or null when rule has no room_type whitelist
     */
    private function extractRoomTypeWhitelist(PromotionRule $rule): ?array
    {
        $applicability = $rule->applicability ?? [];
        $roomTypes = $applicability['room_types'] ?? null;

        if (! is_array($roomTypes) || empty($roomTypes)) {
            return null;
        }

        return array_map('intval', $roomTypes);
    }

    /**
     * Apply a promo code by string. Atomic, race-safe.
     */
    public function applyByCode(
        string $code,
        Purchasable $entity,
        string $email,
        ?int $userId = null,
    ): AppliedAdjustment {
        $normalized = strtoupper(trim($code));

        $promo = PromoCode::query()->where('code', $normalized)->first();

        if (! $promo) {
            throw ValidationException::withMessages([
                'code' => [PromoCodeValidation::ERROR_INVALID_CODE.': Promo code not found.'],
            ]);
        }

        return $this->apply($promo, $entity, $email, $userId);
    }

    /**
     * Apply a PromoCode instance. Atomic, race-safe.
     */
    public function apply(
        PromoCode $promoCode,
        Purchasable $entity,
        string $email,
        ?int $userId = null,
    ): AppliedAdjustment {
        return DB::transaction(function () use ($promoCode, $entity, $email, $userId) {
            // 1. Lock the code row
            /** @var PromoCode $locked */
            $locked = PromoCode::query()
                ->with('promotionRule')
                ->whereKey($promoCode->id)
                ->lockForUpdate()
                ->firstOrFail();

            // 2. Re-validate inside the lock
            $validation = $this->validate($locked->code, $entity, $email, $userId);

            if (! $validation->valid) {
                throw ValidationException::withMessages([
                    'code' => [($validation->errorCode ?? 'INVALID').': '.($validation->message ?? 'Invalid code')],
                ]);
            }

            // 3. Atomic counter increment
            $affected = PromoCode::query()
                ->whereKey($locked->id)
                ->where(function ($q) use ($locked) {
                    if ($locked->usage_limit !== null) {
                        $q->whereRaw('usage_count < ?', [$locked->usage_limit]);
                    }
                })
                ->update(['usage_count' => DB::raw('usage_count + 1')]);

            if ($affected === 0) {
                throw ValidationException::withMessages([
                    'code' => [PromoCodeValidation::ERROR_USAGE_LIMIT_REACHED.': Promo code usage limit reached.'],
                ]);
            }

            $rule = $locked->promotionRule;

            // 3.5. Auto-allocate bonus quantities for buy-x-get-y. Customer adds
            // their paid intent (e.g. 1 room); we expand item qty so cart reflects
            // total received (paid + bonus). PricingService then discounts the
            // bonus units so customer pays only for their original intent.
            $bonusAllocations = [];
            if ($rule->value_type === AdjustmentValueType::BuyXGetY && $entity instanceof Reservation) {
                $bonusAllocations = $this->allocateBuyXGetYBonus($entity, $rule);
            }

            // 4. Create snapshot
            /** @var AppliedAdjustment $adj */
            $adj = AppliedAdjustment::query()->create([
                'adjustable_type' => $entity->getMorphClass(),
                'adjustable_id' => $entity->getKey(),
                'promotion_rule_id' => $rule->id,
                'promo_code_id' => $locked->id,
                'kind' => $rule->kind->value,
                'label' => $rule->name,
                'value_type' => $rule->value_type->value,
                'value' => $rule->value,
                'value_config' => $rule->value_config,
                'base_amount' => 0, // recalculate will fill
                'amount' => 0, // recalculate will fill
                'rule_snapshot' => array_merge(
                    $this->buildRuleSnapshot($rule, $locked),
                    $bonusAllocations ? ['bonus_allocations' => $bonusAllocations] : [],
                ),
                'applied_by' => $userId ? "admin:{$userId}" : 'customer',
            ]);

            // 5. Usage ledger
            PromoCodeUsage::query()->create([
                'promo_code_id' => $locked->id,
                'applied_adjustment_id' => $adj->id,
                'adjustable_type' => $entity->getMorphClass(),
                'adjustable_id' => $entity->getKey(),
                'email' => strtolower(trim($email)),
                'user_id' => $userId,
                'amount_discounted' => 0, // updated after recalc
            ]);

            // 6. Recalculate + persist (computes resolved amount, updates entity totals)
            $result = $this->pricing->recalculateAndPersist($entity);

            // 7. Update usage ledger with resolved amount
            $adj->refresh();
            PromoCodeUsage::query()
                ->where('applied_adjustment_id', $adj->id)
                ->update(['amount_discounted' => $adj->amount]);

            return $adj;
        });
    }

    /**
     * Void an applied adjustment. Reverts promo usage counter if applicable.
     *
     * Pass $recalculate=false to preserve entity totals (e.g. during cancellation
     * when total_amount must be retained for refund calculation).
     */
    public function void(AppliedAdjustment $adjustment, string $reason, bool $recalculate = true): void
    {
        if ($adjustment->isVoided()) {
            return;
        }

        DB::transaction(function () use ($adjustment, $reason, $recalculate) {
            $adjustment->refresh();

            if ($adjustment->isVoided()) {
                return;
            }

            $rule = $adjustment->promotionRule;

            if ($adjustment->promo_code_id && $rule && $rule->revert_usage_on_cancel) {
                PromoCode::query()
                    ->whereKey($adjustment->promo_code_id)
                    ->where('usage_count', '>', 0)
                    ->update(['usage_count' => DB::raw('usage_count - 1')]);

                PromoCodeUsage::query()
                    ->where('applied_adjustment_id', $adjustment->id)
                    ->whereNull('voided_at')
                    ->update(['voided_at' => now()]);
            }

            // Revert bonus item quantities allocated by buy-x-get-y promos.
            $bonusAllocations = $adjustment->rule_snapshot['bonus_allocations'] ?? null;
            if ($bonusAllocations && $adjustment->adjustable instanceof Reservation) {
                $adjustment->adjustable->revertItemBonuses($bonusAllocations);
            }

            $adjustment->update([
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);

            if ($recalculate) {
                $entity = $adjustment->adjustable;

                if ($entity instanceof Purchasable) {
                    $this->pricing->recalculateAndPersist($entity);
                }
            }
        });
    }

    /**
     * Void all active adjustments on a purchasable and revert promo usage counters.
     *
     * Used during reservation/order cancellation. Does NOT recalculate the parent
     * entity to preserve total_amount for refund calculations.
     */
    public function voidAllOnCancel(Purchasable $entity): int
    {
        $count = 0;

        foreach ($entity->adjustments()->whereNull('voided_at')->get() as $adj) {
            $this->void($adj, 'reservation_cancelled', recalculate: false);
            $count++;
        }

        return $count;
    }

    /**
     * Generate a unique random code.
     */
    public function generateUniqueCode(int $length = 8, string $prefix = ''): string
    {
        if ($length < 4 || $length > 60) {
            throw new \InvalidArgumentException('Code length must be between 4 and 60.');
        }

        $maxAttempts = 20;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $random = strtoupper(Str::random($length));
            // strip ambiguous chars (0, O, 1, I, L)
            $random = strtr($random, ['0' => 'A', 'O' => 'B', '1' => 'C', 'I' => 'D', 'L' => 'E']);

            $code = strtoupper(trim($prefix.$random));

            if (! PromoCode::query()->where('code', $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Failed to generate unique code after '.$maxAttempts.' attempts.');
    }

    /**
     * Bulk-generate codes attached to a rule.
     *
     * @param  array{prefix?: string, length?: int, usage_limit?: ?int, usage_limit_per_email?: ?int, valid_from?: ?string, valid_until?: ?string, is_active?: bool, metadata?: ?array}  $options
     * @return Collection<int, PromoCode>
     */
    public function bulkGenerate(PromotionRule $rule, int $quantity, array $options = []): Collection
    {
        if ($quantity < 1 || $quantity > 10000) {
            throw new \InvalidArgumentException('Quantity must be between 1 and 10000.');
        }

        $length = (int) ($options['length'] ?? 8);
        $prefix = (string) ($options['prefix'] ?? '');
        $usageLimit = $options['usage_limit'] ?? 1;
        $usageLimitPerEmail = $options['usage_limit_per_email'] ?? 1;
        $validFrom = $options['valid_from'] ?? null;
        $validUntil = $options['valid_until'] ?? null;
        $isActive = $options['is_active'] ?? true;
        $metadata = $options['metadata'] ?? null;

        $created = new Collection;

        DB::transaction(function () use ($rule, $quantity, $length, $prefix, $usageLimit, $usageLimitPerEmail, $validFrom, $validUntil, $isActive, $metadata, &$created) {
            for ($i = 0; $i < $quantity; $i++) {
                $code = $this->generateUniqueCode($length, $prefix);

                $promo = PromoCode::query()->create([
                    'code' => $code,
                    'promotion_rule_id' => $rule->id,
                    'usage_limit' => $usageLimit,
                    'usage_limit_per_email' => $usageLimitPerEmail,
                    'valid_from' => $validFrom,
                    'valid_until' => $validUntil,
                    'is_active' => $isActive,
                    'metadata' => $metadata,
                    'event_id' => $rule->event_id,
                ]);

                $created->push($promo);
            }
        });

        return $created;
    }

    /**
     * Allocate bonus room quantities on a Reservation for buy-x-get-y promos.
     * For each room item with qty >= buy_qty, adds floor(qty / buy_qty) × get_free_qty
     * bonus rooms. Customer's paid amount stays the same — discount engine subtracts
     * the bonus value at pricing time.
     *
     * @return array<int, array{item_id: int, original_qty: int, bonus_qty: int, new_qty: int}>
     */
    private function allocateBuyXGetYBonus(Reservation $reservation, PromotionRule $rule): array
    {
        $config = $rule->value_config ?? [];
        $buyQty = max(1, (int) ($config['buy_qty'] ?? 1));
        $freeQty = max(1, (int) ($config['get_free_qty'] ?? 1));
        $eligibleRoomTypes = $this->extractRoomTypeWhitelist($rule);

        return $reservation->allocateItemBonuses(function ($item) use ($buyQty, $freeQty, $eligibleRoomTypes) {
            if ($eligibleRoomTypes !== null && ! in_array((int) $item->room_type_id, $eligibleRoomTypes, true)) {
                return 0;
            }

            $originalQty = (int) $item->qty;
            $passes = intdiv($originalQty, $buyQty);

            return $passes * $freeQty;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRuleSnapshot(PromotionRule $rule, PromoCode $code): array
    {
        return [
            'rule_id' => $rule->id,
            'rule_ulid' => $rule->ulid,
            'rule_name' => $rule->name,
            'rule_slug' => $rule->slug,
            'kind' => $rule->kind?->value,
            'value_type' => $rule->value_type?->value,
            'value' => (float) $rule->value,
            'value_config' => $rule->value_config,
            'max_discount_amount' => $rule->max_discount_amount !== null ? (float) $rule->max_discount_amount : null,
            'min_purchase_amount' => $rule->min_purchase_amount !== null ? (float) $rule->min_purchase_amount : null,
            'applies_before_tax' => (bool) $rule->applies_before_tax,
            'stacking_mode' => $rule->stacking_mode?->value,
            'priority' => $rule->priority,
            'trigger_type' => $rule->trigger_type?->value,
            'trigger_config' => $rule->trigger_config,
            'applicability' => $rule->applicability,
            'revert_usage_on_cancel' => (bool) $rule->revert_usage_on_cancel,
            'code' => $code->code,
            'code_ulid' => $code->ulid,
            'snapshot_at' => now()->toIso8601String(),
        ];
    }
}
