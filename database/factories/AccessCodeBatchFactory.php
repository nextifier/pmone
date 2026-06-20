<?php

namespace Database\Factories;

use App\Enums\Ticketing\AccessCodeKind;
use App\Models\AccessCodeBatch;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccessCodeBatch>
 */
class AccessCodeBatchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => $this->faker->words(2, true),
            'kind' => AccessCodeKind::Shared,
            'assigned_to' => null,
            'brand_id' => null,
            'notes' => null,
        ];
    }

    public function invitation(): static
    {
        return $this->state(fn () => ['kind' => AccessCodeKind::Invitation]);
    }
}
