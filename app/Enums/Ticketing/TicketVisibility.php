<?php

namespace App\Enums\Ticketing;

/**
 * Controls whether a ticket is openly purchasable. `public` shows and sells
 * freely; `hidden` is omitted from the public listing until a valid access
 * code reveals it; `code_required` shows but stays locked until a code is
 * supplied at checkout. Legacy tickets default to `public`.
 */
enum TicketVisibility: string
{
    case Public = 'public';
    case Hidden = 'hidden';
    case CodeRequired = 'code_required';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Hidden => 'Hidden (reveal with code)',
            self::CodeRequired => 'Code required',
        };
    }

    /**
     * Whether the ticket needs a valid access code to be purchased.
     */
    public function isGated(): bool
    {
        return $this !== self::Public;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
