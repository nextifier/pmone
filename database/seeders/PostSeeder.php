<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    private array $tags = [
        'laravel', 'php', 'javascript', 'vue', 'react', 'typescript',
        'tutorial', 'guide', 'tips', 'best-practices', 'news', 'update',
        'productivity', 'tools', 'resources', 'inspiration', 'case-study',
        'design', 'ux', 'ui', 'frontend', 'backend', 'api', 'database',
    ];

    public function run(): void
    {
        $this->command->info('Creating blog posts...');

        // Get users who can create posts
        $authors = User::all();

        if ($authors->isEmpty()) {
            $this->command->warn('No users found to create posts. Skipping...');

            return;
        }

        // Get categories
        $categories = Category::all();

        if ($categories->isEmpty()) {
            $this->command->warn('No categories found. Please run CategorySeeder first!');

            return;
        }

        // Create various types of posts
        $postsCount = 50;
        $bar = $this->command->getOutput()->createProgressBar($postsCount);

        for ($i = 0; $i < $postsCount; $i++) {
            $this->createPost($authors, $categories);
            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine();
        $this->command->info("✅ Successfully created $postsCount posts!");
    }

    private function createPost($authors, $categories): void
    {
        // Randomly determine post state
        $states = ['published', 'draft', 'scheduled', 'published'];
        $state = fake()->randomElement($states);

        // Create post with appropriate state
        $post = match ($state) {
            'published' => Post::factory()->published()->create(),
            'scheduled' => Post::factory()->scheduled()->create(),
            default => Post::factory()->create(['status' => 'draft']),
        };

        // Randomly make some posts featured
        if (fake()->boolean(20)) {
            $post->update(['featured' => true]);
        }

        // Randomly make some posts popular
        if (fake()->boolean(15)) {
            $post->update(['view_count' => fake()->numberBetween(100, 5000)]);
        }

        // Randomly set visibility
        $visibility = fake()->randomElement(['public', 'public', 'public', 'members_only', 'private']);
        $post->update(['visibility' => $visibility]);

        // Attach 1-3 authors (co-authors)
        $postAuthors = $authors->random(min(fake()->numberBetween(1, 3), $authors->count()));
        $authorsData = [];
        foreach ($postAuthors as $index => $author) {
            $authorsData[$author->id] = [
                'role' => $index === 0 ? 'primary_author' : fake()->randomElement(['co_author', 'contributor']),
                'order' => $index,
            ];
        }
        $post->authors()->sync($authorsData);

        // Attach 1-3 categories
        $postCategories = $categories->random(min(fake()->numberBetween(1, 3), $categories->count()));
        $categoriesData = [];
        foreach ($postCategories as $index => $category) {
            $categoriesData[$category->id] = [
                'is_primary' => $index === 0,
                'order' => $index,
            ];
        }
        $post->categories()->sync($categoriesData);

        // Attach 2-5 tags
        $postTags = fake()->randomElements($this->tags, fake()->numberBetween(2, 5));
        $post->syncTags($postTags);
    }
}
