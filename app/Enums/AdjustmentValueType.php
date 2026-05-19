<?php

namespace App\Enums;

enum AdjustmentValueType: string
{
    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';
    case BuyXGetY = 'buy_x_get_y';
    case TieredPercentage = 'tiered_percentage';
    case TieredFixedAmount = 'tiered_fixed_amount';
    case BundlePrice = 'bundle_price';
    case FreeAddon = 'free_addon';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Percentage',
            self::FixedAmount => 'Fixed Amount',
            self::BuyXGetY => 'Buy X Get Y Free',
            self::TieredPercentage => 'Tiered Percentage',
            self::TieredFixedAmount => 'Tiered Fixed Amount',
            self::BundlePrice => 'Bundle Price',
            self::FreeAddon => 'Free Add-on',
        };
    }

    /**
     * Whether this value type uses the structured value_config JSON (true) or
     * the simple decimal value column (false).
     */
    public function usesConfig(): bool
    {
        return match ($this) {
            self::Percentage, self::FixedAmount => false,
            default => true,
        };
    }
}
