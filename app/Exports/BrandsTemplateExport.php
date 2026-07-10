<?php

namespace App\Exports;

class BrandsTemplateExport extends BaseTemplateExport
{
    public function array(): array
    {
        return [
            [
                'Levenium',
                'Levenium Lab',
                'hello@levenium.com',
                '+6281234567890',
                'Indonesia',
                'DKI Jakarta',
                'Jakarta Selatan',
                'Jl. Sudirman No. 1',
                'Active',
                'Public',
                'Technology, Digital',
            ],
            [
                'Green Earth',
                'Green Earth Indonesia',
                'info@greenearth.id',
                '+6289876543210',
                'Indonesia',
                'Jawa Barat',
                'Bandung',
                'Jl. Gatot Subroto No. 5',
                'Active',
                'Public',
                'Sustainability, Agriculture',
            ],
        ];
    }

    public function headings(): array
    {
        return [
            'Name',
            'Company Name',
            'Company Email',
            'Company Phone',
            'Country',
            'Province',
            'City',
            'Street Address',
            'Status',
            'Visibility',
            'Business Categories',
        ];
    }

    protected function phoneColumns(): array
    {
        return ['D'];
    }
}
