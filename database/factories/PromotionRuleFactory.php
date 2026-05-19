<?php

namespace Database\Factories;

use App\Enums\AdjustmentKind;
use App\Enums\AdjustmentValueType;
use App\Enums\PenaltyTriggerType;
use App\Enums\StackingMode;
use App\Models\PromotionRule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PromotionRule>
 */
class PromotionRuleFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Promo '.fake()->unique()->word();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 99999),
            'description' => fake()->sentence(),
            'kind' => AdjustmentKind::Discount->value,
            'value_type' => AdjustmentValueType::Percentage->value,
            'value' => fake()->randomFloat(2, 5, 25),
            'max_discount_amount' => null,
            'min_purchase_amount' => null,
            'applies_before_tax' => true,
            'stacking_mode' => StackingMode::Exclusive->value,
            'priority' => 100,
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => true,
            'target_types' => ['Reservation', 'Order'],
            'applicability' => null,
            'trigger_type' => PenaltyTriggerType::None->value,
            'trigger_config' => null,
            'revert_usage_on_cancel' => true,
            'is_system_manual' => false,
            'event_id' => null,
            'project_id' => null,
        ];
    }

    public function discount(): static
    {
        return $this->state(fn () => ['kind' => AdjustmentKind::Discount->value]);
    }

    public function penalty(): static
    {
        return $this->state(fn () => [
            'kind' => AdjustmentKind::Penalty->value,
            'trigger_type' => PenaltyTriggerType::Manual->value,
        ]);
    }

    public function fixedAmount(float $amount): static
    {
        return $this->state(fn () => [
            'value_type' => AdjustmentValueType::FixedAmount->value,
            'value' => $amount,
        ]);
    }

    public function percentage(float $pct): static
    {
        return $this->state(fn () => [
            'value_type' => AdjustmentValueType::Percentage->value,
            'value' => $pct,
        ]);
    }

    public function buyXGetY(int $buy, int $free): static
    {
        return $this->state(fn () => [
            'value_type' => AdjustmentValueType::BuyXGetY->value,
            'value' => 0,
            'value_config' => ['buy_qty' => $buy, 'get_free_qty' => $free],
        ]);
    }

    /**
     * @param  array<int, array{min: float, value: float}>  $tiers
     */
    public function tieredPercentage(array $tiers, string $metric = 'qty'): static
    {
        return $this->state(fn () => [
            'value_type' => AdjustmentValueType::TieredPercentage->value,
            'value' => 0,
            'value_config' => ['metric' => $metric, 'tiers' => $tiers],
        ]);
    }

    public function bundlePrice(int $bundleQty, float $bundlePrice): static
    {
        return $this->state(fn () => [
            'value_type' => AdjustmentValueType::BundlePrice->value,
            'value' => 0,
            'value_config' => ['bundle_qty' => $bundleQty, 'bundle_price' => $bundlePrice],
        ]);
    }

    public function freeAddon(?int $maxQty = null, array $targetLineKeys = ['transfer']): static
    {
        return $this->state(fn () => [
            'value_type' => AdjustmentValueType::FreeAddon->value,
            'value' => 0,
            'value_config' => ['max_free_qty' => $maxQty, 'target_line_keys' => $targetLineKeys],
        ]);
    }
}
