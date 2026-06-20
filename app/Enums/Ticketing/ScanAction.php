<?php

namespace App\Enums\Ticketing;

/**
 * The action recorded in an append-only ScanLog entry when staff scan an
 * attendee's QR. Contextless tokens mean the action is decided by the
 * scanning endpoint, not the token.
 */
enum ScanAction: string
{
    case CheckIn = 'check_in';
    case Reprint = 'reprint';
    case Reissue = 'reissue';

    public function label(): string
    {
        return match ($this) {
            self::CheckIn => 'Check-in',
            self::Reprint => 'Reprint',
            self::Reissue => 'Re-issue',
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
