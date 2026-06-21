<?php

namespace App\Support;

use App\Models\Attendee;
use App\Models\Event;
use Carbon\CarbonInterface;

/**
 * Builds a minimal, dependency-free iCalendar (.ics) document for an event so
 * ticket emails can offer a one-tap "add to calendar". Times are emitted in the
 * event's own timezone (bare TZID, resolved natively by Google/Apple) to match
 * how the app displays them; a day-pass narrows the entry to its single date.
 */
class EventIcs
{
    /**
     * The .ics for the whole event window (or null when the event has no start).
     */
    public static function forEvent(Event $event, ?string $url = null): ?string
    {
        if (! $event->start_date) {
            return null;
        }

        return self::build(
            uid: 'event-'.$event->id.'@pmone.id',
            title: (string) $event->title,
            start: $event->start_date,
            end: $event->end_date ?? $event->start_date,
            timezone: $event->timezone,
            location: self::venue($event),
            description: self::descriptionFor($event),
            url: $url,
        );
    }

    /**
     * The .ics for a single attendee. A day-pass with one chosen day becomes an
     * all-day entry on that date; otherwise it mirrors the full event window.
     */
    public static function forAttendee(Attendee $attendee, ?string $url = null): ?string
    {
        $event = $attendee->ticketOrderItem?->ticketOrder?->event;

        if (! $event || ! $event->start_date) {
            return null;
        }

        $day = $attendee->ticketOrderItem?->selectedEventDay;

        if ($day?->date) {
            return self::build(
                uid: 'attendee-'.$attendee->ulid.'@pmone.id',
                title: (string) $event->title,
                start: $day->date,
                end: $day->date,
                timezone: $event->timezone,
                location: self::venue($event),
                description: self::descriptionFor($event),
                url: $url,
                allDay: true,
            );
        }

        return self::build(
            uid: 'attendee-'.$attendee->ulid.'@pmone.id',
            title: (string) $event->title,
            start: $event->start_date,
            end: $event->end_date ?? $event->start_date,
            timezone: $event->timezone,
            location: self::venue($event),
            description: self::descriptionFor($event),
            url: $url,
        );
    }

    protected static function venue(Event $event): ?string
    {
        return collect([$event->location, $event->hall])
            ->filter()
            ->implode(', ') ?: null;
    }

    protected static function descriptionFor(Event $event): ?string
    {
        $when = collect([$event->date_label, self::timeRange($event), $event->timezone])
            ->filter()
            ->implode(' · ');

        return $when ?: null;
    }

    protected static function timeRange(Event $event): ?string
    {
        if (! $event->start_time) {
            return null;
        }

        return $event->end_time
            ? $event->start_time.' - '.$event->end_time
            : $event->start_time;
    }

    protected static function build(
        string $uid,
        string $title,
        CarbonInterface $start,
        CarbonInterface $end,
        ?string $timezone,
        ?string $location,
        ?string $description,
        ?string $url,
        bool $allDay = false,
    ): string {
        if ($allDay) {
            $dtStart = 'DTSTART;VALUE=DATE:'.$start->format('Ymd');
            $dtEnd = 'DTEND;VALUE=DATE:'.$start->copy()->addDay()->format('Ymd');
        } elseif ($timezone) {
            $dtStart = 'DTSTART;TZID='.$timezone.':'.$start->format('Ymd\THis');
            $dtEnd = 'DTEND;TZID='.$timezone.':'.$end->format('Ymd\THis');
        } else {
            $dtStart = 'DTSTART:'.$start->format('Ymd\THis');
            $dtEnd = 'DTEND:'.$end->format('Ymd\THis');
        }

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//PM One//Tickets//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.$uid,
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            $dtStart,
            $dtEnd,
            'SUMMARY:'.self::escape($title),
        ];

        if ($location) {
            $lines[] = 'LOCATION:'.self::escape($location);
        }

        if ($description) {
            $lines[] = 'DESCRIPTION:'.self::escape($description);
        }

        if ($url) {
            $lines[] = 'URL:'.self::escape($url);
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    protected static function escape(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n", "\r"],
            ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'],
            trim($value),
        );
    }
}
