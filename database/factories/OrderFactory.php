<?php

namespace Database\Factories;

use App\Models\BrandEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 500000, 50000000);
        $taxRate = 11.00;
        $taxAmount = round($subtotal * $taxRate / 100, 2);
        $total = $subtotal + $taxAmount;

        return [
            'brand_event_id' => BrandEvent::factory(),
            'status' => 'submitted',
            'notes' => fake()->optional(0.3)->sentence(),
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'submitted_at' => now(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    public function withDiscount(string $type = 'percentage', float $value = 10): static
    {
        return $this->state(function (array $attributes) use ($type, $value) {
            $subtotal = (float) $attributes['subtotal'];
            $discountAmount = $type === 'percentage'
                ? round($subtotal * $value / 100, 2)
                : min($value, $subtotal);
            $taxRate = (float) $attributes['tax_rate'];
            $taxableAmount = $subtotal - $discountAmount;
            $taxAmount = round($taxableAmount * $taxRate / 100, 2);

            return [
                'discount_type' => $type,
                'discount_value' => $value,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'total' => $taxableAmount + $taxAmount,
            ];
        });
    }
}
