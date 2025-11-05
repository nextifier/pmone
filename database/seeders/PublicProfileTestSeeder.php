<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

class PublicProfileTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create or update test project
        $project = Project::updateOrCreate(
            ['username' => 'panoramamedia'],
            [
                'name' => 'Antonius Richardo',
                'bio' => 'Full-stack Developer at Panorama Media.',
                'email' => 'anton@panoramamedia.com',
                'phone' => [
                    ['label' => 'Sales', 'number' => '+6281234567890'],
                ],
                'status' => 'active',
                'visibility' => 'public',
            ]
        );

        // Clear existing links
        $project->links()->delete();

        // Add social media links
        $socialLinks = [
            ['label' => 'Website', 'url' => 'https://panoramamedia.com', 'order' => 0],
            ['label' => 'Instagram', 'url' => 'https://instagram.com/panoramamedia', 'order' => 1],
            ['label' => 'Facebook', 'url' => 'https://facebook.com/panoramamedia', 'order' => 2],
            ['label' => 'X', 'url' => 'https://x.com/panoramamedia', 'order' => 3],
            ['label' => 'LinkedIn', 'url' => 'https://linkedin.com/company/panoramamedia', 'order' => 4],
        ];

        // Add custom links
        $customLinks = [
            ['label' => 'Custom Link 1', 'url' => 'https://example.com/link1', 'order' => 5],
            ['label' => 'Custom Link 2', 'url' => 'https://example.com/link2', 'order' => 6],
            ['label' => 'Custom Link 3', 'url' => 'https://example.com/link3', 'order' => 7],
            ['label' => 'Custom Link 4', 'url' => 'https://example.com/link4', 'order' => 8],
        ];

        $allLinks = array_merge($socialLinks, $customLinks);

        foreach ($allLinks as $linkData) {
            $project->links()->create([
                'label' => $linkData['label'],
                'url' => $linkData['url'],
                'order' => $linkData['order'],
                'is_active' => true,
            ]);
        }

        $this->command->info('Public profile test data created successfully!');
        $this->command->info('Visit: http://localhost:3000/projects/panoramamedia');
    }
}
