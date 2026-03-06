<?php

namespace Database\Factories;

use App\Models\EventDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventDocumentSubmission>
 */
class EventDocumentSubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $document = EventDocument::factory()->create();

        return [
            'event_document_id' => $document->id,
            'booth_identifier' => fake()->numerify('B-###'),
            'event_id' => $document->event_id,
            'document_version' => 1,
            'submitted_by' => User::factory(),
            'submitted_at' => now(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }

    public function agreed(): static
    {
        return $this->state(fn (array $attributes) => [
            'agreed_at' => now(),
        ]);
    }

    public function withTextValue(?string $value = null): static
    {
        return $this->state(fn (array $attributes) => [
            'text_value' => $value ?? fake()->sentence(),
        ]);
    }
}
