<?php

namespace App\Services\Rundown;

use App\Models\Event;
use Illuminate\Support\Collection;

class RundownGrouper
{
    /**
     * Group rundown items by date and add sequential day_number + day_label.
     *
     * When $event has start_date and end_date, day_number is computed from
     * the diff to start_date (1-indexed) and ALL days in the range are
     * emitted even if they have no items.
     *
     * Items with null date are placed in an "Unscheduled" bucket appended
     * at the end. Pass $unscheduledLabel = null to skip the bucket entirely.
     *
     * @param  Collection  $items  RundownItem collection (already filtered by event)
     * @param  callable  $itemTransformer  fn($item) => array
     * @param  Event|null  $event  Source event for day-number anchor
     * @param  string|null  $unscheduledLabel  Label for null-date bucket
     * @return array<int, array<string, mixed>>
     */
    public static function group(
        Collection $items,
        callable $itemTransformer,
        ?Event $event = null,
        ?string $unscheduledLabel = 'Unscheduled',
    ): array {
        $sorted = $items->sortBy(function ($item) {
            return [
                $item->date?->format('Y-m-d') ?? '9999-99-99',
                $item->order_column ?? PHP_INT_MAX,
                $item->start_time ?? '99:99:99',
            ];
        })->values();

        $grouped = $sorted->groupBy(fn ($item) => $item->date?->format('Y-m-d') ?? '_unscheduled');

        $eventStart = $event?->start_date?->copy()->startOfDay();
        $eventEnd = $event?->end_date?->copy()->startOfDay();

        $days = [];

        if ($eventStart && $eventEnd) {
            // Emit a row for every day in the event range, even if empty.
            $cursor = $eventStart->copy();
            $dayNumber = 1;

            while ($cursor->lte($eventEnd)) {
                $key = $cursor->format('Y-m-d');
                $dayItems = $grouped->get($key, collect());

                $days[] = [
                    'date' => $key,
                    'day_number' => $dayNumber,
                    'day_label' => "Day {$dayNumber}",
                    'items' => $dayItems->map($itemTransformer)->values()->all(),
                ];

                $cursor->addDay();
                $dayNumber++;
            }

            // Items with a date outside the event range are intentionally
            // dropped from the day list — the date validator now blocks new
            // entries from being created out of range, and surfacing legacy
            // out-of-range rows would produce nonsensical "Day 102"-style
            // labels. Items will be picked up via the unscheduled bucket
            // below if their date is null.
        } else {
            // Fallback: no event reference — number sequentially from earliest date.
            $uniqueDates = $sorted->pluck('date')
                ->map(fn ($d) => $d?->format('Y-m-d'))
                ->unique()
                ->filter()
                ->values();

            $dateToNumber = $uniqueDates->mapWithKeys(fn ($d, $i) => [$d => $i + 1])->all();

            foreach ($grouped as $dateKey => $dayItems) {
                if ($dateKey === '_unscheduled') {
                    continue;
                }

                $dayNumber = $dateToNumber[$dateKey] ?? null;

                $days[] = [
                    'date' => $dateKey,
                    'day_number' => $dayNumber,
                    'day_label' => "Day {$dayNumber}",
                    'items' => $dayItems->map($itemTransformer)->values()->all(),
                ];
            }
        }

        if ($unscheduledLabel !== null && $grouped->has('_unscheduled')) {
            $days[] = [
                'date' => null,
                'day_number' => null,
                'day_label' => $unscheduledLabel,
                'items' => $grouped->get('_unscheduled')->map($itemTransformer)->values()->all(),
            ];
        }

        return $days;
    }
}
