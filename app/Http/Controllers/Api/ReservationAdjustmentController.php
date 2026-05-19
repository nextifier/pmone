<?php

namespace App\Http\Controllers\Api;

use App\Enums\AdjustmentKind;
use App\Enums\StackingMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Adjustment\StoreAdjustmentRequest;
use App\Http\Resources\AppliedAdjustmentResource;
use App\Models\AppliedAdjustment;
use App\Models\Event;
use App\Models\PromotionRule;
use App\Models\Reservation;
use App\Services\Pricing\PricingService;
use App\Services\Promotion\PenaltyService;
use App\Services\Promotion\PromoCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReservationAdjustmentController extends Controller
{
    public function __construct(
        protected PromoCodeService $promoCodes,
        protected PenaltyService $penalties,
        protected PricingService $pricing,
    ) {}

    public function store(StoreAdjustmentRequest $request, Event $event, Reservation $reservation): JsonResponse
    {
        $this->ensureBelongsToEvent($event, $reservation);

        $data = $request->validated();
        $mode = $data['mode'];

        if ($mode === 'promo_code') {
            $adjustment = $this->promoCodes->applyByCode(
                $data['promo_code'],
                $reservation->fresh(['items', 'transfers', 'adjustments.promotionRule', 'hotel']),
                $data['email'],
                $request->user()->id,
            );

            $reservation->forceFill([
                'promo_code_applied' => strtoupper(trim($data['promo_code'])),
            ])->save();
        } elseif ($mode === 'promotion_rule') {
            $rule = PromotionRule::query()->findOrFail($data['promotion_rule_id']);
            $adjustment = $this->penalties->applyManual(
                $reservation->fresh(['items', 'transfers', 'adjustments.promotionRule', 'hotel']),
                $rule,
                isset($data['override_value']) ? (float) $data['override_value'] : null,
                appliedByUserId: $request->user()->id,
                reason: $data['reason'] ?? null,
            );
        } else {
            $adjustment = $this->createManualAdhocAdjustment($reservation, $data, $request->user()->id);
        }

        $reservation = $reservation->fresh(['items', 'transfers', 'adjustments', 'hotel', 'event']);

        activity()
            ->causedBy($request->user())
            ->performedOn($reservation)
            ->event('adjustment_applied')
            ->withProperties([
                'project_id' => $event->project_id,
                'reservation_id' => $reservation->id,
                'adjustment_id' => $adjustment->id,
                'mode' => $mode,
                'kind' => $adjustment->kind,
                'amount' => (float) $adjustment->amount,
                'promo_code' => $data['promo_code'] ?? null,
                'promotion_rule_id' => $data['promotion_rule_id'] ?? null,
                'reason' => $data['reason'] ?? null,
            ])
            ->log('Adjustment applied to reservation');

        return response()->json([
            'message' => 'Adjustment applied successfully',
            'data' => [
                'adjustment' => (new AppliedAdjustmentResource($adjustment->fresh(['promotionRule', 'promoCode'])))->resolve(),
                'reservation_totals' => [
                    'subtotal_rooms' => (float) $reservation->subtotal_rooms,
                    'subtotal_transfer' => (float) $reservation->subtotal_transfer,
                    'surcharge' => (float) $reservation->surcharge_amount,
                    'penalty' => (float) $reservation->penalty_amount,
                    'discount' => (float) $reservation->discount_amount,
                    'tax' => (float) $reservation->tax_amount,
                    'service' => (float) $reservation->service_charge_amount,
                    'total' => (float) $reservation->total_amount,
                ],
            ],
        ], 201);
    }

    public function destroy(Event $event, Reservation $reservation, AppliedAdjustment $adjustment): JsonResponse
    {
        $this->ensureBelongsToEvent($event, $reservation);
        $this->ensureAdjustmentBelongsToReservation($reservation, $adjustment);

        if (! auth()->user()?->can('promotions.void_adjustment')) {
            abort(403);
        }

        $voidedKind = $adjustment->kind;
        $voidedAmount = (float) $adjustment->amount;
        $voidedAdjustmentId = $adjustment->id;

        $this->promoCodes->void($adjustment, 'admin_voided');

        $reservation = $reservation->fresh(['items', 'transfers', 'adjustments', 'hotel', 'event']);

        activity()
            ->causedBy(auth()->user())
            ->performedOn($reservation)
            ->event('adjustment_voided')
            ->withProperties([
                'project_id' => $event->project_id,
                'reservation_id' => $reservation->id,
                'adjustment_id' => $voidedAdjustmentId,
                'kind' => $voidedKind,
                'amount' => $voidedAmount,
                'reason' => 'admin_voided',
            ])
            ->log('Adjustment voided on reservation');

        return response()->json([
            'message' => 'Adjustment voided',
            'data' => [
                'reservation_totals' => [
                    'total' => (float) $reservation->total_amount,
                    'discount' => (float) $reservation->discount_amount,
                    'penalty' => (float) $reservation->penalty_amount,
                ],
            ],
        ]);
    }

    private function createManualAdhocAdjustment(Reservation $reservation, array $data, int $userId): AppliedAdjustment
    {
        $kind = $data['kind'];
        $valueType = $data['value_type'];
        $value = (float) $data['value'];
        $reason = $data['reason'] ?? null;

        $slug = $kind === AdjustmentKind::Discount->value
            ? 'system-manual-reservation-discount'
            : 'system-manual-reservation-penalty';

        $rule = PromotionRule::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $kind === 'discount' ? 'Manual Reservation Discount' : 'Manual Reservation Penalty',
                'kind' => $kind,
                'value_type' => $valueType,
                'value' => 0,
                'applies_before_tax' => true,
                'stacking_mode' => StackingMode::CombinableWithAll->value,
                'priority' => 200,
                'is_active' => true,
                'is_system_manual' => true,
                'target_types' => ['Reservation'],
                'trigger_type' => 'manual',
                'revert_usage_on_cancel' => false,
            ]
        );

        $adjustment = null;

        DB::transaction(function () use ($reservation, $rule, $kind, $valueType, $value, $reason, $userId, &$adjustment) {
            $adjustment = AppliedAdjustment::query()->create([
                'adjustable_type' => $reservation->getMorphClass(),
                'adjustable_id' => $reservation->id,
                'promotion_rule_id' => $rule->id,
                'promo_code_id' => null,
                'kind' => $kind,
                'label' => trim(($kind === 'discount' ? 'Manual Discount' : 'Manual Penalty').($reason ? " - {$reason}" : '')),
                'value_type' => $valueType,
                'value' => $value,
                'value_config' => null,
                'base_amount' => $reservation->subtotalForDiscountBase(),
                'amount' => 0,
                'rule_snapshot' => array_merge($rule->toArray(), [
                    'override_value' => $value,
                    'override_value_type' => $valueType,
                    'reason' => $reason,
                ]),
                'applied_by' => "admin:{$userId}",
            ]);

            $this->pricing->recalculateAndPersist($reservation->fresh(['adjustments', 'hotel']));
        });

        return $adjustment;
    }

    private function ensureBelongsToEvent(Event $event, Reservation $reservation): void
    {
        if ($reservation->event_id !== $event->id) {
            abort(404);
        }
    }

    private function ensureAdjustmentBelongsToReservation(Reservation $reservation, AppliedAdjustment $adjustment): void
    {
        if ($adjustment->adjustable_type !== $reservation->getMorphClass()
            || $adjustment->adjustable_id !== $reservation->id) {
            abort(404);
        }
    }
}
