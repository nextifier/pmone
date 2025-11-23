<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostAutosave;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PostAutosave>
 */
class PostAutosaveFactory extends Factory
{
    protected $model = PostAutosave::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => null, // Default to new post
            'user_id' => User::factory(),
            'title' => fake()->sentence(rand(3, 8)),
            'excerpt' => fake()->paragraph(3),
            'content' => fake()->paragraphs(rand(5, 15), true),
            'content_format' => fake()->randomElement(['html', 'markdown', 'lexical']),
            'meta_title' => fake()->sentence(),
            'meta_description' => fake()->text(160),
            'status' => 'draft',
            'visibility' => 'public',
            'published_at' => null,
            'featured' => false,
            'reading_time' => fake()->numberBetween(1, 15),
            'settings' => [],
            'tmp_media' => null,
            'tags' => null,
            'authors' => null,
        ];
    }

    /**
     * Indicate that this autosave is for an existing post.
     */
    public function forExistingPost(?Post $post = null): static
    {
        return $this->state(fn (array $attributes) => [
            'post_id' => $post?->id ?? Post::factory(),
        ]);
    }

    /**
     * Indicate that this autosave is for a new post.
     */
    public function forNewPost(): static
    {
        return $this->state(fn (array $attributes) => [
            'post_id' => null,
        ]);
    }
}
