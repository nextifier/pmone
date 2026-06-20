<?php

namespace App\Enums\Ticketing;

/**
 * Lifecycle status of an access code. `active` codes can be redeemed;
 * `revoked` codes are forward-blocked (existing held/paid orders keep their
 * tickets, but no new redemption is allowed).
 */
enum AccessCodeStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Revoked => 'Revoked',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Revoked => 'destructive',
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
