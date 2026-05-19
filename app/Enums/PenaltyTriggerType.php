<?php

namespace App\Enums;

enum PenaltyTriggerType: string
{
    case None = 'none';
    case Manual = 'manual';
    case BookingWindow = 'booking_window';
    case EventPeriod = 'event_period';
    case DateRange = 'date_range';
    case LeadTime = 'lead_time';
    case CancellationWindow = 'cancellation_window';

    public function label(): string
    {
        return match ($this) {
            self::None => 'None',
            self::Manual => 'Manual',
            self::BookingWindow => 'Booking Window',
            self::EventPeriod => 'Event Period',
            self::DateRange => 'Date Range',
            self::LeadTime => 'Lead Time',
            self::CancellationWindow => 'Cancellation Window',
        };
    }

    public function isAutoEvaluated(): bool
    {
        return in_array($this, [
            self::BookingWindow,
            self::EventPeriod,
            self::DateRange,
            self::LeadTime,
        ], true);
    }

    public function description(): string
    {
        return match ($this) {
            self::None => 'No automatic trigger',
            self::Manual => 'Admin manually triggers',
            self::BookingWindow => 'Triggers when purchase placed within configured window',
            self::EventPeriod => 'Triggers when current time is within event onsite period',
            self::DateRange => 'Triggers when purchase placed between fixed dates',
            self::LeadTime => 'Triggers when booked too close to check-in date',
            self::CancellationWindow => 'Triggers on cancellation within specified window',
        };
    }
}
