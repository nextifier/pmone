<?php

namespace Database\Seeders;

use App\Models\GaProperty;
use App\Models\Project;
use Illuminate\Database\Seeder;

class GaPropertySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get first project or create one if none exists
        $project = Project::first() ?? Project::factory()->create();

        $properties = [
            [
                'project_id' => $project->id,
                'name' => 'Main Website',
                'property_id' => '123456789',
                'is_active' => true,
                'sync_frequency' => 10,
            ],
            [
                'project_id' => $project->id,
                'name' => 'Blog Website',
                'property_id' => '987654321',
                'is_active' => true,
                'sync_frequency' => 15,
            ],
            [
                'project_id' => $project->id,
                'name' => 'E-commerce Store',
                'property_id' => '555666777',
                'is_active' => true,
                'sync_frequency' => 5,
            ],
        ];

        foreach ($properties as $property) {
            GaProperty::create($property);
        }

        // Create additional random properties for testing
        GaProperty::factory()->count(12)->create();
    }
}
