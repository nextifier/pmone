<?php

namespace Database\Factories;

use App\Models\BrandEvent;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
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
            'operational_status' => 'submitted',
            'notes' => fake()->optional(0.3)->sentence(),
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'currency' => 'IDR',
            'exchange_rate_to_idr' => 1,
            'total_idr' => $total,
            'submitted_at' => now(),
        ];
    }

    /**
     * Denominate the order in USD with an FX snapshot (default 16000 IDR/USD).
     */
    public function usd(float $rate = 16000): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => 'USD',
            'exchange_rate_to_idr' => $rate,
            'total_idr' => round((float) $attributes['total'] * $rate, 2),
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'operational_status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'operational_status' => 'cancelled',
            'cancellation_reason' => fake()->sentence(),
        ]);
    }

    public function withDiscount(float $amount = 100000): static
    {
        return $this->state(function (array $attributes) use ($amount) {
            $subtotal = (float) $attributes['subtotal'];
            $discountAmount = min($amount, $subtotal);
            $taxRate = (float) $attributes['tax_rate'];
            $taxableAmount = $subtotal - $discountAmount;
            $taxAmount = round($taxableAmount * $taxRate / 100, 2);
            $total = $taxableAmount + $taxAmount;
            $rate = (float) ($attributes['exchange_rate_to_idr'] ?? 1);

            return [
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'total_idr' => round($total * $rate, 2),
            ];
        });
    }
}
