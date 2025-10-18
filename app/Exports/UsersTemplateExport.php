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

class UsersTemplateExport implements FromArray, WithColumnFormatting, WithEvents, WithHeadings, WithStyles
{
    public function array(): array
    {
        // Return sample data - values will be set explicitly as text in AfterSheet event
        return [
            [
                'John Doe',
                'johndoe',
                'john.doe@example.com',
                'user',
                '+6281234567890',
                '1990-01-15',
                'male',
                'active',
                'public',
                'A passionate developer from Jakarta',
                'https://johndoe.com',
                'https://instagram.com/johndoe',
            ],
            [
                'Jane Smith',
                'janesmith',
                'jane.smith@example.com',
                'admin,staff',
                '+6287654321098',
                '1995-05-20',
                'female',
                'active',
                'private',
                'Tech enthusiast and designer',
                'https://janesmith.dev',
                'https://instagram.com/janesmith',
            ],
        ];
    }

    public function headings(): array
    {
        return [
            'name',
            'username',
            'email',
            'roles',
            'phone',
            'birth_date',
            'gender',
            'status',
            'visibility',
            'bio',
            'website',
            'instagram',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'E' => NumberFormat::FORMAT_TEXT, // phone
            'F' => NumberFormat::FORMAT_TEXT, // birth_date
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Set column E and F as text format explicitly
        $sheet->getStyle('E:E')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('F:F')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Set entire columns E and F as text format BEFORE setting values
                $sheet->getStyle('E:E')->getNumberFormat()->setFormatCode('@');
                $sheet->getStyle('F:F')->getNumberFormat()->setFormatCode('@');

                // Explicitly set phone values (column E) as text
                $sheet->setCellValueExplicit('E2', '+6281234567890', DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('E3', '+6287654321098', DataType::TYPE_STRING);

                // Explicitly set birth_date values (column F) as text
                $sheet->setCellValueExplicit('F2', '1990-01-15', DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('F3', '1995-05-20', DataType::TYPE_STRING);
            },
        ];
    }
}
