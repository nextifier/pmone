<?php

namespace App\Enums\Ticketing;

/**
 * Whether a ticket grants venue entry or is a paid extra consumed inside the event.
 */
enum TicketKind: string
{
    case Entry = 'entry';
    case AddOn = 'add_on';

    public function label(): string
    {
        return match ($this) {
            self::Entry => 'Entry',
            self::AddOn => 'Add-on',
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
