<?php

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(rand(3, 8));
        $content = fake()->paragraphs(rand(5, 15), true);
        $excerpt = fake()->paragraph(3);

        return [
            'title' => rtrim($title, '.'),
            'excerpt' => $excerpt,
            'content' => $content,
            'content_format' => fake()->randomElement(['html', 'markdown']),
            'status' => 'draft',
            'visibility' => 'public',
            'published_at' => null,
            'featured' => false,
            'og_type' => 'article',
            'source' => 'native',
        ];
    }

    /**
     * Indicate that the post is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'published_at' => fake()->dateTimeBetween('-6 months', 'now'),
        ]);
    }

    /**
     * Indicate that the post is scheduled.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'scheduled',
            'published_at' => fake()->dateTimeBetween('now', '+1 month'),
        ]);
    }

    /**
     * Indicate that the post is featured.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'featured' => true,
        ]);
    }

    /**
     * Indicate that the post is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'archived',
        ]);
    }

    /**
     * Indicate that the post is private.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'private',
        ]);
    }

    /**
     * Indicate that the post is members only.
     */
    public function membersOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'members_only',
        ]);
    }

    /**
     * Indicate that the post is imported from Ghost.
     */
    public function fromGhost(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'ghost',
            'source_id' => fake()->uuid(),
            'content_format' => 'html',
        ]);
    }

    /**
     * Indicate that the post is imported from Canvas.
     */
    public function fromCanvas(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'canvas',
            'source_id' => fake()->uuid(),
            'content_format' => 'markdown',
        ]);
    }

    /**
     * Indicate that the post has high view count.
     */
    public function popular(): static
    {
        return $this->state(fn (array $attributes) => [
            'view_count' => fake()->numberBetween(1000, 50000),
        ]);
    }
}
