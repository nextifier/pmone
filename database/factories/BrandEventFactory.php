<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BrandEvent>
 */
class BrandEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'event_id' => Event::factory(),
            'booth_number' => fake()->optional(0.7)->numerify('B-###'),
            'booth_size' => fake()->optional(0.5)->randomFloat(2, 4, 100),
            // Deliberately null, not random. Document visibility is gated on
            // booth type, so a factory that handed out one of five values at
            // random made every test touching that gate a coin flip - it cost
            // EventDocumentTest and DocumentMiniFormTest an intermittent
            // failure each. Tests that need a type ask for one with boothType().
            'booth_type' => null,
            'status' => 'draft',
        ];
    }

    /**
     * A booth of a specific type. Pass nothing for a plain raw space.
     */
    public function boothType(string $type = 'raw_space'): static
    {
        return $this->state(fn (array $attributes) => [
            'booth_type' => $type,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    /**
     * Pin the exhibitor's billing currency, overriding country-based resolution.
     */
    public function currencyOverride(string $currency): static
    {
        return $this->state(fn (array $attributes) => [
            'currency_override' => $currency,
        ]);
    }
}
