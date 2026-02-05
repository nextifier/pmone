<?php

namespace App\Exports;

class ContactFormSubmissionsTemplateExport extends BaseTemplateExport
{
    public function array(): array
    {
        return [
            [
                'John Doe',
                'SUNKOP',
                'john@example.com',
                '6281234567890',
                'Cafe & Brasserie Expo',
                'Exhibitor Registration - Cafe & Brasserie Expo',
                'New',
                '2025-01-15 10:30',
            ],
            [
                'Jane Smith',
                'Coffee House',
                'jane@example.com',
                '6289876543210',
                'Indonesia Anime Con',
                'Exhibitor Registration - Indonesia Anime Con',
                'In Progress',
                '2025-01-20 14:45',
            ],
            [
                'Bob Wilson',
                'Tea Factory',
                'bob@example.com',
                '6281122334455',
                'CampX',
                'Outing Inquiry - CampX',
                'Completed',
                '2025-02-01 09:15',
            ],
        ];
    }

    public function headings(): array
    {
        return [
            'Name',
            'Brand Name',
            'Email',
            'Phone',
            'Project',
            'Subject',
            'Status',
            'Created At',
        ];
    }

    protected function phoneColumns(): array
    {
        return ['D'];
    }
}
