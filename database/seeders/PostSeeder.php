<?php

namespace Database\Seeders;

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

        // Create various types of posts
        $postsCount = 50;
        $bar = $this->command->getOutput()->createProgressBar($postsCount);

        for ($i = 0; $i < $postsCount; $i++) {
            $this->createPost($authors);
            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine();
        $this->command->info("✅ Successfully created $postsCount posts!");
    }

    private function createPost($authors): void
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

        // Randomly set visibility
        $visibility = fake()->randomElement(['public', 'public', 'public', 'members_only', 'private']);
        $post->update(['visibility' => $visibility]);

        // Attach 2-5 tags with 'post' type
        $postTags = fake()->randomElements($this->tags, fake()->numberBetween(2, 5));
        $post->syncTagsWithType($postTags, 'post');
    }
}
