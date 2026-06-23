<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserNote>
 */
class UserNoteFactory extends Factory
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
            'author_id' => User::factory(),
            'body' => $this->faker->sentence(),
        ];
    }
}
