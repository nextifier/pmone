<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProjectsTemplateExport implements FromArray, WithColumnFormatting, WithEvents, WithHeadings, WithStyles
{
    public function array(): array
    {
        // Return sample data
        return [
            [
                'Keramika Indonesia',
                'keramika-indonesia',
                'info@keramika.co.id',
                '+6281234567890',
                'active',
                'public',
                'Traditional Indonesian ceramics and pottery',
                '2024-01-15',
            ],
            [
                'Indonesia Coffee Festival',
                'indonesia-coffee-festival',
                'contact@coffeefestival.id',
                '+6287654321098',
                'active',
                'public',
                'Annual coffee festival celebrating Indonesian coffee',
                '2024-02-20',
            ],
        ];
    }

    public function headings(): array
    {
        return [
            'name',
            'username',
            'email',
            'phone',
            'status',
            'visibility',
            'bio',
            'start_date',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'D' => NumberFormat::FORMAT_TEXT, // phone
            'H' => NumberFormat::FORMAT_TEXT, // start_date
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Set column D and H as text format explicitly
        $sheet->getStyle('D:D')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('H:H')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Set entire columns D and H as text format BEFORE setting values
                $sheet->getStyle('D:D')->getNumberFormat()->setFormatCode('@');
                $sheet->getStyle('H:H')->getNumberFormat()->setFormatCode('@');

                // Explicitly set phone values (column D) as text
                $sheet->setCellValueExplicit('D2', '+6281234567890', DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('D3', '+6287654321098', DataType::TYPE_STRING);

                // Explicitly set start_date values (column H) as text
                $sheet->setCellValueExplicit('H2', '2024-01-15', DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('H3', '2024-02-20', DataType::TYPE_STRING);
            },
        ];
    }
}
