<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faq>
 */
class FaqFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $question = rtrim(fake()->sentence(6), '.').'?';
        $answer = '<p>'.fake()->sentence(15).'</p>';

        return [
            'event_id' => Event::factory(),
            'question' => ['en' => $question, 'id' => $question],
            'answer' => ['en' => $answer, 'id' => $answer],
            'settings' => [],
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
