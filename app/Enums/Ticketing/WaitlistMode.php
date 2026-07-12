<?php

namespace App\Enums\Ticketing;

/**
 * Per-event behavior when a waitlisted seat frees up. `auto_offer` (default)
 * atomically reserves the seat for the next FIFO entry and emails a
 * time-limited claim link (Plan 020 option A). `notify_only` never reserves
 * anything - it just emails "available again" and lets whoever buys first
 * win (Plan 020 option B), which small events may prefer over the added
 * claim-window machinery.
 */
enum WaitlistMode: string
{
    case AutoOffer = 'auto_offer';
    case NotifyOnly = 'notify_only';

    public function label(): string
    {
        return match ($this) {
            self::AutoOffer => 'Auto-offer (hold the seat)',
            self::NotifyOnly => 'Notify only (first to buy wins)',
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
