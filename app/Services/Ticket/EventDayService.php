<?php

namespace App\Services\Ticket;

use App\Models\Event;
use App\Models\EventDay;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EventDayService
{
    /**
     * Idempotently derive the event's days from its start_date..end_date range
     * (evaluated in the event's own timezone). Days are matched on `date`, so
     * existing EventDay ids are NEVER recreated - tickets that reference a day
     * keep working. Days that fall outside the new range are deactivated
     * (is_active = false) rather than deleted, so historical references survive.
     *
     * @return Collection<int, EventDay>
     */
    public function syncFromEventDates(Event $event): Collection
    {
        if (! $event->start_date || ! $event->end_date) {
            return $event->eventDays()->orderBy('date')->get();
        }

        $timezone = $event->timezone ?: config('app.timezone');
        $start = $event->start_date->copy()->setTimezone($timezone)->startOfDay();
        $end = $event->end_date->copy()->setTimezone($timezone)->startOfDay();

        if ($end->lessThan($start)) {
            [$start, $end] = [$end, $start];
        }

        $targetDates = [];
        for ($cursor = $start->copy(); $cursor->lessThanOrEqualTo($end); $cursor->addDay()) {
            $targetDates[] = $cursor->format('Y-m-d');
        }

        return DB::transaction(function () use ($event, $targetDates) {
            $existing = $event->eventDays()->get()->keyBy(fn (EventDay $day) => $day->date->format('Y-m-d'));
            $nextNumber = (int) $event->eventDays()->max('day_number');
            $inRangeIds = [];

            foreach ($targetDates as $dateString) {
                $day = $existing->get($dateString);

                if ($day) {
                    $inRangeIds[] = $day->id;
                    if (! $day->is_active) {
                        $day->update(['is_active' => true]);
                    }

                    continue;
                }

                $created = $event->eventDays()->create([
                    'date' => $dateString,
                    'day_number' => ++$nextNumber,
                    'is_active' => true,
                ]);
                $inRangeIds[] = $created->id;
            }

            $event->eventDays()
                ->whereNotIn('id', $inRangeIds ?: [0])
                ->update(['is_active' => false]);

            return $event->eventDays()->orderBy('date')->get();
        });
    }

    /**
     * Set exactly the given day ids active and everything else inactive.
     *
     * @param  array<int, int>  $activeIds
     * @return Collection<int, EventDay>
     */
    public function setActiveDays(Event $event, array $activeIds): Collection
    {
        DB::transaction(function () use ($event, $activeIds) {
            if (! empty($activeIds)) {
                $event->eventDays()->whereIn('id', $activeIds)->update(['is_active' => true]);
            }

            $event->eventDays()->whereNotIn('id', $activeIds ?: [0])->update(['is_active' => false]);
        });

        return $event->eventDays()->orderBy('date')->get();
    }
}
