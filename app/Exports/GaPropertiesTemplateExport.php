<?php

namespace App\Exports;

class GaPropertiesTemplateExport extends BaseTemplateExport
{
    public function array(): array
    {
        return [
            [
                '1',
                'My Website',
                '123456789',
                'production, website',
                'Active',
                '10',
            ],
            [
                '1',
                'Another Property',
                '987654321',
                'production, mobile',
                'Active',
                '15',
            ],
            [
                '1',
                'Disabled Property',
                '555555555',
                'development, staging',
                'Inactive',
                '30',
            ],
        ];
    }

    public function headings(): array
    {
        return [
            'Project ID',
            'Name',
            'Property ID',
            'Tags (comma-separated)',
            'Status',
            'Sync Frequency (minutes)',
        ];
    }
}
