<?php

namespace App\Observers;

use App\Enums\AdjustmentKind;
use App\Enums\AdjustmentValueType;
use App\Enums\PenaltyTriggerType;
use App\Enums\StackingMode;
use App\Models\Event;
use App\Models\PromotionRule;

class EventObserver
{
    /**
     * Columns that, when touched, require the onsite-penalty rule to be (re)synced.
     * The onsite window fields matter because an onsite penalty can only ever be
     * charged once that window is configured (done via Order Form Settings).
     *
     * @var array<int, string>
     */
    private const SYNC_TRIGGER_COLUMNS = [
        'onsite_penalty_rate',
        'onsite_order_opens_at',
        'onsite_order_closes_at',
    ];

    /**
     * Create the onsite-penalty rule when an event is created with an explicit rate.
     *
     * The actual penalty charged on an order comes from PenaltyService reading the
     * "event-onsite-penalty" rule (kind=penalty, trigger=event_period, phase=onsite),
     * not the live Event column. Keeping them in sync guarantees what exhibitors see
     * equals what they are charged.
     */
    public function created(Event $event): void
    {
        if ($event->onsite_penalty_rate !== null && (float) $event->onsite_penalty_rate > 0) {
            $this->syncOnsitePenaltyRule($event);
        }
    }

    /**
     * Re-sync whenever the rate or the onsite ordering window changes. Configuring the
     * window via Order Form Settings is what makes any onsite penalty possible, so it
     * is the right moment to lazily ensure the backing rule exists for existing events.
     */
    public function updated(Event $event): void
    {
        if ($event->wasChanged(self::SYNC_TRIGGER_COLUMNS)) {
            $this->syncOnsitePenaltyRule($event);
        }
    }

    protected function syncOnsitePenaltyRule(Event $event): void
    {
        $rate = (float) ($event->onsite_penalty_rate ?? 0);

        $rule = PromotionRule::query()
            ->withTrashed()
            ->where('event_id', $event->id)
            ->where('kind', AdjustmentKind::Penalty->value)
            ->where('trigger_type', PenaltyTriggerType::EventPeriod->value)
            ->where('trigger_config->phase', 'onsite')
            ->first();

        if (! $rule) {
            if ($rate <= 0) {
                return;
            }

            PromotionRule::create([
                'name' => "{$event->title} - On-site Penalty",
                'slug' => "event-onsite-penalty-{$event->slug}",
                'description' => 'Auto-synced from Event.onsite_penalty_rate (Order Form Settings).',
                'kind' => AdjustmentKind::Penalty,
                'value_type' => AdjustmentValueType::Percentage,
                'value' => $rate,
                'applies_before_tax' => true,
                'stacking_mode' => StackingMode::CombinableWithAll,
                'priority' => 50,
                'is_active' => true,
                'target_types' => ['Order'],
                'trigger_type' => PenaltyTriggerType::EventPeriod,
                'trigger_config' => ['phase' => 'onsite'],
                'revert_usage_on_cancel' => true,
                'is_system_manual' => false,
                'event_id' => $event->id,
            ]);

            return;
        }

        $rule->value = $rate;
        $rule->is_active = $rate > 0;
        $rule->save();
    }
}
