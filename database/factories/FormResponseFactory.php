<?php

namespace Database\Factories;

use App\Models\Form;
use App\Models\FormResponse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormResponse>
 */
class FormResponseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'response_data' => [],
            'respondent_email' => fake()->optional(0.5)->safeEmail(),
            'browser_fingerprint' => fake()->optional(0.7)->sha1(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'status' => FormResponse::STATUS_NEW,
            'submitted_at' => now(),
        ];
    }

    public function status(string $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }
}
