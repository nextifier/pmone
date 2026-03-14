<?php

namespace App\Exports;

class ContactsTemplateExport extends BaseTemplateExport
{
    public function array(): array
    {
        return [
            [
                'John Doe',
                'Marketing Manager',
                'john@example.com, john.doe@company.com',
                '+6281234567890, +6289876543210',
                'PT Maju Bersama',
                'https://majubersama.com',
                'Indonesia',
                'DKI Jakarta',
                'Jakarta Selatan',
                'Jl. Sudirman No. 1',
                'Active',
                'event',
                'exhibitor',
                'Technology, Digital',
                'VIP, priority',
                'Megabuild Indonesia, Solartech Indonesia',
                'Met at Megabuild 2026',
            ],
            [
                'Jane Smith',
                'CEO',
                'jane@greenearth.id',
                '+6281122334455',
                'Green Earth Indonesia',
                'https://greenearth.id',
                'Singapore',
                '',
                '',
                '10 Bayfront Avenue',
                'Active',
                'referral',
                'sponsor, media-partner',
                'Sustainability, Agriculture',
                '',
                'Megabuild Indonesia',
                'Referred by John Doe',
            ],
        ];
    }

    public function headings(): array
    {
        return [
            'Name',
            'Job Title',
            'Emails',
            'Phones',
            'Company Name',
            'Website',
            'Country',
            'Province',
            'City',
            'Street Address',
            'Status',
            'Source',
            'Contact Types',
            'Business Categories',
            'Tags',
            'Projects',
            'Notes',
        ];
    }

    protected function phoneColumns(): array
    {
        return ['D'];
    }
}
