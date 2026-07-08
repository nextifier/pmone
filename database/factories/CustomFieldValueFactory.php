<?php

namespace Database\Factories;

use App\Models\Attendee;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomFieldValue>
 */
class CustomFieldValueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'custom_field_id' => CustomField::factory(),
            'subject_type' => User::class,
            'subject_id' => User::factory(),
            'value' => [fake()->words(2, true)],
        ];
    }

    public function forUser(?User $user = null): static
    {
        return $this->state(fn () => [
            'subject_type' => User::class,
            'subject_id' => $user?->id ?? User::factory(),
        ]);
    }

    public function forAttendee(?Attendee $attendee = null): static
    {
        return $this->state(fn () => [
            'subject_type' => Attendee::class,
            'subject_id' => $attendee?->id ?? Attendee::factory(),
        ]);
    }
}
