<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserPageView;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserPageView>
 */
class UserPageViewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'path' => '/'.fake()->slug(2),
            'title' => fake()->words(2, true),
            'visited_at' => now(),
        ];
    }
}
