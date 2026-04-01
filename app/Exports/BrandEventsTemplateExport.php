<?php

namespace App\Exports;

class BrandEventsTemplateExport extends BaseTemplateExport
{
    public function array(): array
    {
        return [
            [
                'Brand A',
                'PT Brand A Indonesia',
                'brand-a@example.com',
                '628123456789',
                'pic@example.com',
                'A-01',
                '9',
                'Raw Space',
                'Active',
                'Leading franchise brand in Indonesia specializing in coffee and beverages.',
                'https://example.com/logos/brand-a.png',
                'https://brand-a.com',
                'https://www.instagram.com/branda',
                'https://www.tiktok.com/@branda',
                'https://www.facebook.com/branda',
                'https://x.com/branda',
                'https://www.linkedin.com/company/branda',
                'https://www.youtube.com/@branda',
                'Food & Beverage, Franchise',
                'Premium coffee franchise with modern concept and affordable pricing.',
                '25',
                '2018',
                'Rp 150.000.000 - Rp 300.000.000',
            ],
            [
                'Brand B',
                'CV Brand B',
                'brand-b@example.com',
                '628987654321',
                'john@example.com',
                'B-02',
                '6',
                'Standard Shell Scheme',
                'Active',
                'Premium skincare and beauty products.',
                'https://example.com/logos/brand-b.png',
                'https://brand-b.com',
                'https://www.instagram.com/brandb',
                '',
                '',
                '',
                '',
                '',
                'Beauty, Health',
                '',
                '10',
                '2020',
                '',
            ],
            [
                'Brand C',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
            ],
        ];
    }

    public function headings(): array
    {
        return [
            'Brand Name',
            'Company Name',
            'Company Email',
            'Company Phone',
            'PIC Email',
            'Booth Number',
            'Booth Size (sqm)',
            'Booth Type',
            'Status',
            'Description',
            'Brand Logo',
            'Website',
            'Instagram',
            'TikTok',
            'Facebook',
            'X',
            'LinkedIn',
            'YouTube',
            'Business Categories',
            'Business Concept',
            'Branch Total',
            'Establishment Year',
            'Investment Fee',
        ];
    }

    protected function phoneColumns(): array
    {
        return ['D'];
    }
}
