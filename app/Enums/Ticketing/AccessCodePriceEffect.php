<?php

namespace App\Enums\Ticketing;

/**
 * Optional price effect an access code applies on top of the active price
 * phase, scoped only to the unlocked tickets in the cart. `none` gates access
 * without touching price; `set_price` overrides the unit price (0 => free
 * Claim); `percentage`/`amount` discount the unlocked lines.
 */
enum AccessCodePriceEffect: string
{
    case None = 'none';
    case SetPrice = 'set_price';
    case Percentage = 'percentage';
    case Amount = 'amount';

    public function label(): string
    {
        return match ($this) {
            self::None => 'No price effect (gate only)',
            self::SetPrice => 'Set price',
            self::Percentage => 'Percentage off',
            self::Amount => 'Amount off',
        };
    }

    /**
     * Whether this effect changes the price (anything but `none`).
     */
    public function affectsPrice(): bool
    {
        return $this !== self::None;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
