<?php

namespace Database\Seeders;

use App\Models\GaProperty;
use Illuminate\Database\Seeder;

class GaPropertySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $properties = [
            [
                'name' => 'Main Website',
                'property_id' => '123456789',
                'account_name' => 'Company Main Account',
                'is_active' => true,
                'sync_frequency' => 10,
                'rate_limit_per_hour' => 12,
            ],
            [
                'name' => 'Blog Website',
                'property_id' => '987654321',
                'account_name' => 'Company Main Account',
                'is_active' => true,
                'sync_frequency' => 15,
                'rate_limit_per_hour' => 12,
            ],
            [
                'name' => 'E-commerce Store',
                'property_id' => '555666777',
                'account_name' => 'Company Secondary Account',
                'is_active' => true,
                'sync_frequency' => 5,
                'rate_limit_per_hour' => 15,
            ],
        ];

        foreach ($properties as $property) {
            GaProperty::create($property);
        }

        // Create additional random properties for testing
        GaProperty::factory()->count(12)->create();
    }
}
