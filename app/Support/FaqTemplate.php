<?php

namespace App\Support;

use App\Models\Event;

/**
 * Resolves FAQ template variables ({{token}}) against an event's context so
 * FAQ content stays in sync with PM One event/project data (single source of
 * truth). Unknown tokens are left untouched; known-but-empty tokens resolve to
 * an empty string.
 */
class FaqTemplate
{
    /**
     * Token => human label, surfaced to the admin form as a variable legend.
     *
     * @return array<string, string>
     */
    public static function tokens(): array
    {
        return [
            'event_title' => 'Event title',
            'event_date' => 'Event date',
            'event_time' => 'Event time',
            'event_location' => 'Event location',
            'event_hall' => 'Event hall',
            'location_link' => 'Location map link',
            'contact_email' => 'Contact email',
            'whatsapp_link' => 'WhatsApp link',
            'instagram' => 'Instagram link',
        ];
    }

    public static function render(?string $text, Event $event): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        $map = self::variables($event);

        return preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/',
            fn (array $m): string => $map[$m[1]] ?? $m[0],
            $text
        );
    }

    /**
     * @return array<string, string>
     */
    public static function variables(Event $event): array
    {
        $project = $event->project;
        $links = $project?->links ?? collect();

        $whatsapp = $links->first(
            fn ($link) => str_contains(strtolower((string) $link->label), 'whatsapp')
        );
        $instagram = $links->first(
            fn ($link) => strtolower((string) $link->label) === 'instagram'
        );

        $time = trim(implode(' - ', array_filter([$event->start_time, $event->end_time])), ' -');

        return [
            'event_title' => (string) ($event->title ?? ''),
            'event_date' => (string) ($event->date_label ?? ''),
            'event_time' => $time,
            'event_location' => (string) ($event->location ?? ''),
            'event_hall' => (string) ($event->hall ?? ''),
            'location_link' => (string) ($event->location_link ?? ''),
            'contact_email' => (string) ($project?->email ?? ''),
            'whatsapp_link' => (string) ($whatsapp?->url ?? ''),
            'instagram' => (string) ($instagram?->url ?? ''),
        ];
    }
}
