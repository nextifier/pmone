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
                'project_id' => 1,
                'name' => 'Panorama Media',
                'property_id' => '408286802',
                'is_active' => true,
                'sync_frequency' => 10,
            ],
            [
                'project_id' => 2,
                'name' => 'Panorama Events',
                'property_id' => '436650892',
                'is_active' => true,
                'sync_frequency' => 10,
            ],
            [
                'project_id' => 3,
                'name' => 'Panorama Live',
                'property_id' => '460220076',
                'is_active' => true,
                'sync_frequency' => 10,
            ],
            [
                'project_id' => 4,
                'name' => 'Megabuild Indonesia',
                'property_id' => '358178518',
                'is_active' => true,
                'sync_frequency' => 10,
            ],
            [
                'project_id' => 5,
                'name' => 'Keramika Indonesia',
                'property_id' => '358199976',
                'is_active' => true,
                'sync_frequency' => 10,
            ],
            [
                'project_id' => 6,
                'name' => 'Franchise & License Expo Indonesia',
                'property_id' => '358210404',
                'is_active' => true,
                'sync_frequency' => 10,
            ],
            [
                'project_id' => 7,
                'name' => 'Cafe & Brasserie Expo',
                'property_id' => '358218613',
                'is_active' => true,
                'sync_frequency' => 10,
            ],
            [
                'project_id' => 8,
                'name' => 'Indonesia Coffee Festival',
                'property_id' => '358223315',
                'is_active' => true,
                'sync_frequency' => 10,
            ],
            [
                'project_id' => 9,
                'name' => 'Cokelat Expo Indonesia',
                'property_id' => '480978377',
                'is_active' => true,
                'sync_frequency' => 10,
            ],
            [
                'project_id' => 10,
                'name' => 'More Food Expo',
                'property_id' => '495976336',
                'is_active' => true,
                'sync_frequency' => 10,
            ],
            [
                'project_id' => 12,
                'name' => 'Indonesia Outing Expo',
                'property_id' => '490866004',
                'is_active' => true,
                'sync_frequency' => 10,
            ],
            [
                'project_id' => 13,
                'name' => 'Indonesia Comic Con',
                'property_id' => '358202717',
                'is_active' => true,
                'sync_frequency' => 10,
            ],
            [
                'project_id' => 14,
                'name' => 'Indonesia Anime Con',
                'property_id' => '424560462',
                'is_active' => true,
                'sync_frequency' => 10,
            ],
        ];

        foreach ($properties as $property) {
            GaProperty::updateOrCreate(
                ['property_id' => $property['property_id']],
                $property
            );
        }
    }
}
