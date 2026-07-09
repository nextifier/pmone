<?php

namespace Database\Factories;

use App\Enums\EmailSuppressionReason;
use App\Models\EmailSuppression;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailSuppression>
 */
class EmailSuppressionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'reason' => EmailSuppressionReason::Bounce,
            'subtype' => 'Permanent',
            'source' => 'ses',
            'suppressed_at' => now(),
            'payload' => null,
        ];
    }

    public function complaint(): static
    {
        return $this->state(fn () => [
            'reason' => EmailSuppressionReason::Complaint,
            'subtype' => 'abuse',
        ]);
    }

    public function manual(): static
    {
        return $this->state(fn () => [
            'reason' => EmailSuppressionReason::Manual,
            'subtype' => null,
            'source' => 'admin',
        ]);
    }
}
