<?php

namespace Database\Seeders;

use App\Models\Link;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LinkSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating links for users and projects...');

        // Create links for users (50% chance for each user)
        $users = User::all();
        $userLinksCreated = 0;

        foreach ($users as $user) {
            if (fake()->boolean(50)) {
                $linksCount = $this->createLinksForEntity($user);
                $userLinksCreated += $linksCount;
            }
        }

        // Create links for projects (80% chance for each project)
        $projects = Project::all();
        $projectLinksCreated = 0;

        foreach ($projects as $project) {
            if (fake()->boolean(80)) {
                $linksCount = $this->createLinksForEntity($project);
                $projectLinksCreated += $linksCount;
            }
        }

        $this->command->info("✅ Created $userLinksCreated links for users");
        $this->command->info("✅ Created $projectLinksCreated links for projects");
    }

    private function createLinksForEntity($entity): int
    {
        $faker = fake();
        $username = $entity->username ?? Str::slug($entity->name);
        $name = $entity->name;

        $linkOptions = [
            ['label' => 'Website', 'chance' => 40, 'url' => $faker->url()],
            ['label' => 'LinkedIn', 'chance' => 60, 'url' => 'https://linkedin.com/in/'.Str::slug($name)],
            ['label' => 'Instagram', 'chance' => 50, 'url' => 'https://instagram.com/'.$username],
            ['label' => 'X', 'chance' => 35, 'url' => 'https://twitter.com/'.$username],
            ['label' => 'GitHub', 'chance' => 25, 'url' => 'https://github.com/'.$username],
            ['label' => 'YouTube', 'chance' => 20, 'url' => 'https://youtube.com/@'.$username],
            ['label' => 'Facebook', 'chance' => 30, 'url' => 'https://facebook.com/'.$username],
        ];

        $order = 0;
        $createdCount = 0;

        foreach ($linkOptions as $linkOption) {
            if ($faker->boolean($linkOption['chance'])) {
                Link::create([
                    'linkable_type' => get_class($entity),
                    'linkable_id' => $entity->id,
                    'label' => $linkOption['label'],
                    'url' => $linkOption['url'],
                    'order' => $order++,
                    'is_active' => $faker->boolean(95),
                ]);
                $createdCount++;
            }
        }

        return $createdCount;
    }
}
