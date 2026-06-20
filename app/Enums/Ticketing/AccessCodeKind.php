<?php

namespace App\Enums\Ticketing;

/**
 * Kind of access code: a `shared` code is one string handed to many people
 * (gated by max_uses + validity), an `invitation` code is unique per person
 * (max_uses = 1, optionally bound to an email/phone).
 */
enum AccessCodeKind: string
{
    case Shared = 'shared';
    case Invitation = 'invitation';

    public function label(): string
    {
        return match ($this) {
            self::Shared => 'Shared code',
            self::Invitation => 'Invitation (unique per person)',
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
