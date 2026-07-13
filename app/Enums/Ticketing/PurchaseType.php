<?php

namespace App\Enums\Ticketing;

/**
 * How a ticket is purchased: through an external platform (redirect) or
 * first-party via the platform's own cart + payment gateway.
 */
enum PurchaseType: string
{
    case External = 'external';
    case FirstParty = 'first_party';

    public function label(): string
    {
        return match ($this) {
            self::External => 'External platform',
            self::FirstParty => 'First-party ('.config('app.name').')',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
