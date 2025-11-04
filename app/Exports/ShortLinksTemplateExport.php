<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ShortLinksTemplateExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
{
    public function headings(): array
    {
        return [
            'Slug',
            'Destination URL',
            'Status',
        ];
    }

    public function array(): array
    {
        // Return example rows
        return [
            [
                'my-link',
                'https://example.com',
                'active',
            ],
            [
                'promo-2024',
                'https://example.com/promo',
                'active',
            ],
            [
                'disabled-link',
                'https://example.com/old',
                'inactive',
            ],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Set default font
        $sheet->getParent()->getDefaultStyle()->getFont()
            ->setName('Open Sans')
            ->setSize(14);

        // Style header row
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 16,
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E8E8E8'],
                ],
            ],
        ];
    }
}
