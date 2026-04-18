<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class AppSettingSeeder extends Seeder
{
    public function run(): void
    {
        AppSetting::set('branding', [
            'logo_url' => null,
            'company_name' => 'PM One',
            'address' => 'Jakarta, Indonesia',
            'city' => 'Jakarta',
            'country' => 'Indonesia',
            'phone' => '+62 21 0000 0000',
            'email' => 'info@pmone.id',
            'website' => 'https://pmone.id',
            'tax_id' => null,
            'bank_accounts' => [
                ['bank_name' => 'BCA', 'account_number' => '0000000000', 'account_name' => 'PT PM One'],
            ],
            'footer_note' => 'Thank you for your business.',
            'primary_color' => '#0F172A',
        ], 'PM One global branding (logo, company info, bank accounts) for Invoice & Receipt PDF.');

        $this->command->info('Seeded global branding settings.');
    }
}
