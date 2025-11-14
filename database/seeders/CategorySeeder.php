<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    private array $categories = [
        [
            'name' => 'Technology',
            'description' => 'All about technology, gadgets, and digital innovation',
            'children' => [
                ['name' => 'Programming', 'description' => 'Coding, software development, and best practices'],
                ['name' => 'Web Development', 'description' => 'Frontend, backend, and full-stack development'],
                ['name' => 'Mobile Development', 'description' => 'iOS, Android, and cross-platform apps'],
                ['name' => 'AI & Machine Learning', 'description' => 'Artificial intelligence and ML technologies'],
            ],
        ],
        [
            'name' => 'Design',
            'description' => 'UI/UX design, graphic design, and creative work',
            'children' => [
                ['name' => 'UI/UX Design', 'description' => 'User interface and experience design'],
                ['name' => 'Graphic Design', 'description' => 'Visual design and branding'],
            ],
        ],
        [
            'name' => 'Business',
            'description' => 'Business strategies, entrepreneurship, and management',
            'children' => [
                ['name' => 'Entrepreneurship', 'description' => 'Starting and growing businesses'],
                ['name' => 'Marketing', 'description' => 'Digital marketing and growth strategies'],
                ['name' => 'Finance', 'description' => 'Business finance and investment'],
            ],
        ],
        [
            'name' => 'Lifestyle',
            'description' => 'Life, health, and personal development',
            'children' => [
                ['name' => 'Health & Fitness', 'description' => 'Physical and mental wellness'],
                ['name' => 'Travel', 'description' => 'Travel guides and adventures'],
                ['name' => 'Food & Cooking', 'description' => 'Recipes and culinary experiences'],
            ],
        ],
        [
            'name' => 'Education',
            'description' => 'Learning resources and educational content',
        ],
        [
            'name' => 'Entertainment',
            'description' => 'Movies, music, games, and pop culture',
        ],
    ];

    public function run(): void
    {
        $this->command->info('Creating blog categories...');

        // Get a creator user
        $creator = User::role(['master', 'admin'])->first();

        if (! $creator) {
            $this->command->warn('No eligible user found to create categories. Skipping...');

            return;
        }

        $totalCount = 0;
        foreach ($this->categories as $categoryData) {
            $totalCount++;
            if (isset($categoryData['children'])) {
                $totalCount += count($categoryData['children']);
            }
        }

        $bar = $this->command->getOutput()->createProgressBar($totalCount);

        foreach ($this->categories as $categoryData) {
            $category = Category::create([
                'name' => $categoryData['name'],
                'description' => $categoryData['description'],
                'visibility' => 'public',
                'created_by' => $creator->id,
            ]);

            $bar->advance();

            // Create child categories if any
            if (isset($categoryData['children'])) {
                foreach ($categoryData['children'] as $childData) {
                    Category::create([
                        'name' => $childData['name'],
                        'description' => $childData['description'],
                        'parent_id' => $category->id,
                        'visibility' => 'public',
                        'created_by' => $creator->id,
                    ]);

                    $bar->advance();
                }
            }
        }

        $bar->finish();
        $this->command->newLine();
        $this->command->info("✅ Successfully created $totalCount categories!");
    }
}
