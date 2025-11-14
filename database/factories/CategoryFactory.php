<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = [
            'Technology', 'Programming', 'Design', 'Business', 'Marketing',
            'Science', 'Health', 'Lifestyle', 'Travel', 'Food',
            'Education', 'Entertainment', 'Sports', 'Politics', 'Finance',
        ];

        return [
            'name' => fake()->unique()->randomElement($categories),
            'description' => fake()->sentence(20),
            'visibility' => 'public',
            'parent_id' => null,
        ];
    }

    /**
     * Indicate that the category is private.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'private',
        ]);
    }

    /**
     * Indicate that the category is a child of another category.
     */
    public function child(): static
    {
        return $this->state(function (array $attributes) {
            $parent = Category::factory()->create();

            return [
                'parent_id' => $parent->id,
            ];
        });
    }
}
