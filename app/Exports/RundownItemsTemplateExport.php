<?php

namespace App\Exports;

class RundownItemsTemplateExport extends BaseTemplateExport
{
    public function array(): array
    {
        return [
            [
                '2026-05-01',
                '09:00',
                '10:00',
                'Opening Keynote',
                'Pidato Pembukaan',
                'Welcome session',
                'Sesi pembukaan',
                'Welcoming address from the organizer.',
                'Sambutan dari penyelenggara.',
                'Innovation',
                'Inovasi',
                'Main Hall',
                'Aula Utama',
                'Acme Corp',
                'Acme Corp',
                'John Doe',
                'John Doe',
                '[{"name":"Jane Roe","title":"CEO"}]',
                '[{"name":"John Doe","title":"Founder","organization":"Acme"}]',
                'keynote, opening',
                'yes',
            ],
        ];
    }

    public function headings(): array
    {
        return [
            'Date',
            'Start Time',
            'End Time',
            'Title (EN)',
            'Title (ID)',
            'Subtitle (EN)',
            'Subtitle (ID)',
            'Description (EN)',
            'Description (ID)',
            'Theme (EN)',
            'Theme (ID)',
            'Location (EN)',
            'Location (ID)',
            'Presented By (EN)',
            'Presented By (ID)',
            'Moderator (EN)',
            'Moderator (ID)',
            'Panelists (JSON)',
            'Speakers (JSON)',
            'Categories',
            'Active',
        ];
    }
}
