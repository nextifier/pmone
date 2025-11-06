<?php

namespace App\Exports;

class GaPropertiesTemplateExport extends BaseTemplateExport
{
    public function array(): array
    {
        return [
            [
                'My Website',
                '123456789',
                'My GA4 Account',
                'Active',
                '10',
                '12',
            ],
            [
                'Another Property',
                '987654321',
                'Another Account',
                'Active',
                '15',
                '10',
            ],
            [
                'Disabled Property',
                '555555555',
                'Test Account',
                'Inactive',
                '30',
                '6',
            ],
        ];
    }

    public function headings(): array
    {
        return [
            'Name',
            'Property ID',
            'Account Name',
            'Status',
            'Sync Frequency (minutes)',
            'Rate Limit Per Hour',
        ];
    }
}
