<?php

namespace App\Services\Hotel;

use App\Models\Hotel;
use App\Models\HotelEventAllotment;
use Illuminate\Support\Collection;

class AllotmentService
{
    /**
     * Get hotels that have active allotments for given event and date range.
     */
    public function getAvailableHotelsForEvent(int $eventId, string $checkIn, string $checkOut): Collection
    {
        return Hotel::query()
            ->active()
            ->where('event_id', $eventId)
            ->whereHas('allotments', function ($q) use ($checkIn, $checkOut) {
                $q->active()
                    ->where('start_date', '<=', $checkIn)
                    ->where('end_date', '>=', $checkOut);
            })
            ->with(['media', 'roomTypes' => fn ($q) => $q->active()])
            ->get();
    }

    /**
     * Mark allotments past release_at as inactive.
     */
    public function releaseExpiredAllotments(): int
    {
        return HotelEventAllotment::query()
            ->where('is_active', true)
            ->whereNotNull('release_at')
            ->where('release_at', '<=', now())
            ->update(['is_active' => false]);
    }
}
