<?php

namespace Database\Factories;

use App\Enums\ContactFormStatus;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ContactFormSubmission>
 */
class ContactFormSubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'form_data' => [
                'name' => fake()->name(),
                'email' => fake()->safeEmail(),
                'phone' => fake()->phoneNumber(),
                'message' => fake()->paragraphs(3, true),
            ],
            'subject' => fake()->randomElement([
                'New Contact Form Submission',
                'Product Inquiry',
                'Service Request',
                'General Inquiry',
                'Partnership Opportunity',
            ]),
            'status' => ContactFormStatus::New->value,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }

    /**
     * Indicate that the submission is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContactFormStatus::InProgress->value,
        ]);
    }

    /**
     * Indicate that the submission is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContactFormStatus::Completed->value,
            'followed_up_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Indicate that the submission is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContactFormStatus::Archived->value,
        ]);
    }

    /**
     * Indicate that the submission has been followed up.
     */
    public function followedUp(): static
    {
        return $this->state(fn (array $attributes) => [
            'followed_up_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Submission with brand name field (extended form).
     */
    public function withBrandName(): static
    {
        return $this->state(fn (array $attributes) => [
            'form_data' => array_merge($attributes['form_data'], [
                'brand_name' => fake()->company(),
            ]),
        ]);
    }

    /**
     * Submission with product category field (extended form).
     */
    public function withProductCategory(): static
    {
        return $this->state(fn (array $attributes) => [
            'form_data' => array_merge($attributes['form_data'], [
                'product_category' => fake()->randomElement([
                    'Electronics',
                    'Fashion',
                    'Food & Beverage',
                    'Services',
                    'Technology',
                ]),
            ]),
        ]);
    }
}
