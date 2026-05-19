<?php

namespace App\Http\Controllers\Api;

use App\Enums\AdjustmentKind;
use App\Enums\StackingMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Adjustment\StoreAdjustmentRequest;
use App\Http\Resources\AppliedAdjustmentResource;
use App\Http\Resources\OrderResource;
use App\Models\AppliedAdjustment;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\Order;
use App\Models\Project;
use App\Models\PromotionRule;
use App\Services\Pricing\PricingService;
use App\Services\Promotion\PenaltyService;
use App\Services\Promotion\PromoCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class OrderAdjustmentController extends Controller
{
    public function __construct(
        protected PromoCodeService $promoCodes,
        protected PenaltyService $penalties,
        protected PricingService $pricing,
    ) {}

    public function store(StoreAdjustmentRequest $request, string $username, string $eventSlug, string $ulid): JsonResponse
    {
        [$event, $order] = $this->resolveEventAndOrder($username, $eventSlug, $ulid);

        $data = $request->validated();
        $mode = $data['mode'];

        if ($mode === 'promo_code') {
            $adjustment = $this->promoCodes->applyByCode(
                $data['promo_code'],
                $order->fresh(['adjustments', 'brandEvent.brand']),
                $data['email'],
                $request->user()->id,
            );

            $order->forceFill([
                'promo_code_applied' => strtoupper(trim($data['promo_code'])),
            ])->save();
        } elseif ($mode === 'promotion_rule') {
            $rule = PromotionRule::query()->findOrFail($data['promotion_rule_id']);
            $adjustment = $this->penalties->applyManual(
                $order->fresh(['adjustments', 'brandEvent.brand']),
                $rule,
                isset($data['override_value']) ? (float) $data['override_value'] : null,
                appliedByUserId: $request->user()->id,
                reason: $data['reason'] ?? null,
            );
        } else {
            $adjustment = $this->createManualAdhocAdjustment($order, $data, $request->user()->id);
        }

        $order = $order->fresh(['items.productCategory', 'brandEvent.brand', 'creator', 'adjustments']);

        activity()
            ->causedBy($request->user())
            ->performedOn($order)
            ->event('adjustment_applied')
            ->withProperties([
                'project_id' => $event->project_id,
                'order_id' => $order->id,
                'adjustment_id' => $adjustment->id,
                'mode' => $mode,
                'kind' => $adjustment->kind,
                'amount' => (float) $adjustment->amount,
                'promo_code' => $data['promo_code'] ?? null,
                'promotion_rule_id' => $data['promotion_rule_id'] ?? null,
                'reason' => $data['reason'] ?? null,
            ])
            ->log('Adjustment applied to order');

        return response()->json([
            'message' => 'Adjustment applied successfully',
            'data' => [
                'adjustment' => (new AppliedAdjustmentResource($adjustment->fresh(['promotionRule', 'promoCode'])))->resolve(),
                'order' => (new OrderResource($order))->resolve(),
            ],
        ], 201);
    }

    public function destroy(string $username, string $eventSlug, string $ulid, AppliedAdjustment $adjustment): JsonResponse
    {
        [$event, $order] = $this->resolveEventAndOrder($username, $eventSlug, $ulid);

        if ($adjustment->adjustable_type !== $order->getMorphClass() || $adjustment->adjustable_id !== $order->id) {
            abort(404);
        }

        if (! auth()->user()?->can('promotions.void_adjustment')) {
            abort(403);
        }

        $voidedKind = $adjustment->kind;
        $voidedAmount = (float) $adjustment->amount;
        $voidedAdjustmentId = $adjustment->id;

        $this->promoCodes->void($adjustment, 'admin_voided');

        $order = $order->fresh(['items.productCategory', 'brandEvent.brand', 'creator', 'adjustments']);

        activity()
            ->causedBy(auth()->user())
            ->performedOn($order)
            ->event('adjustment_voided')
            ->withProperties([
                'project_id' => $event->project_id,
                'order_id' => $order->id,
                'adjustment_id' => $voidedAdjustmentId,
                'kind' => $voidedKind,
                'amount' => $voidedAmount,
                'reason' => 'admin_voided',
            ])
            ->log('Adjustment voided on order');

        return response()->json([
            'message' => 'Adjustment voided',
            'data' => (new OrderResource($order))->resolve(),
        ]);
    }

    /**
     * @return array{0: Event, 1: Order}
     */
    private function resolveEventAndOrder(string $username, string $eventSlug, string $ulid): array
    {
        $project = Project::query()->where('username', $username)->firstOrFail();
        $event = Event::query()->where('slug', $eventSlug)->where('project_id', $project->id)->firstOrFail();

        $order = Order::query()
            ->whereIn('brand_event_id', BrandEvent::query()->where('event_id', $event->id)->select('id'))
            ->where('ulid', $ulid)
            ->firstOrFail();

        return [$event, $order];
    }

    private function createManualAdhocAdjustment(Order $order, array $data, int $userId): AppliedAdjustment
    {
        $kind = $data['kind'];
        $valueType = $data['value_type'];
        $value = (float) $data['value'];
        $reason = $data['reason'] ?? null;

        $slug = $kind === AdjustmentKind::Discount->value
            ? 'system-manual-order-discount'
            : 'system-manual-order-penalty';

        $rule = PromotionRule::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $kind === 'discount' ? 'Manual Order Discount' : 'Manual Order Penalty',
                'kind' => $kind,
                'value_type' => $valueType,
                'value' => 0,
                'applies_before_tax' => true,
                'stacking_mode' => StackingMode::CombinableWithAll->value,
                'priority' => 200,
                'is_active' => true,
                'is_system_manual' => true,
                'target_types' => ['Order'],
                'trigger_type' => 'manual',
                'revert_usage_on_cancel' => false,
            ]
        );

        $adjustment = null;

        DB::transaction(function () use ($order, $rule, $kind, $valueType, $value, $reason, $userId, &$adjustment) {
            $adjustment = AppliedAdjustment::query()->create([
                'adjustable_type' => $order->getMorphClass(),
                'adjustable_id' => $order->id,
                'promotion_rule_id' => $rule->id,
                'promo_code_id' => null,
                'kind' => $kind,
                'label' => trim(($kind === 'discount' ? 'Manual Discount' : 'Manual Penalty').($reason ? " - {$reason}" : '')),
                'value_type' => $valueType,
                'value' => $value,
                'base_amount' => (float) $order->subtotal,
                'amount' => 0,
                'rule_snapshot' => array_merge($rule->toArray(), [
                    'override_value' => $value,
                    'override_value_type' => $valueType,
                    'reason' => $reason,
                ]),
                'applied_by' => "admin:{$userId}",
            ]);

            $this->pricing->recalculateAndPersist($order->fresh(['adjustments', 'brandEvent']));
        });

        return $adjustment;
    }
}
