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
                'Active',
                'event',
                'exhibitor',
                'Technology, Digital',
            ],
            [
                'Jane Smith',
                'CEO',
                'jane@greenearth.id',
                '+6281122334455',
                'Green Earth Indonesia',
                'https://greenearth.id',
                'Active',
                'referral',
                'sponsor, media-partner',
                'Sustainability, Agriculture',
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
            'Status',
            'Source',
            'Contact Types',
            'Business Categories',
        ];
    }

    protected function phoneColumns(): array
    {
        return ['D'];
    }
}
