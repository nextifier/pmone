<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventDocument>
 */
class EventDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'title' => fake()->randomElement([
                'Event Rules & Regulations',
                'Company Profile Form',
                'Insurance Certificate',
                'Floor Plan Approval',
                'Electrical Layout',
                'Risk Assessment',
            ]),
            'document_type' => fake()->randomElement(['file_upload', 'checkbox_agreement', 'text_input']),
            'is_required' => fake()->boolean(60),
            'blocks_next_step' => false,
            'submission_deadline' => fake()->optional(0.5)->dateTimeBetween('+1 week', '+3 months'),
        ];
    }

    public function eventRule(): static
    {
        return $this->state(fn (array $attributes) => [
            'document_type' => 'checkbox_agreement',
            'blocks_next_step' => true,
            'is_required' => true,
        ]);
    }

    public function fileUpload(): static
    {
        return $this->state(fn (array $attributes) => [
            'document_type' => 'file_upload',
        ]);
    }

    public function textInput(): static
    {
        return $this->state(fn (array $attributes) => [
            'document_type' => 'text_input',
        ]);
    }

    public function forBoothTypes(array $boothTypes): static
    {
        return $this->state(fn (array $attributes) => [
            'booth_types' => $boothTypes,
        ]);
    }
}
