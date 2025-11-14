<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PostRevision>
 */
class PostRevisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'title' => fake()->sentence(rand(3, 8)),
            'excerpt' => fake()->paragraph(3),
            'content' => fake()->paragraphs(rand(5, 15), true),
            'revision_number' => 1,
            'created_by' => User::factory(),
        ];
    }
}
