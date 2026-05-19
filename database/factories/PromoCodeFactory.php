<?php

namespace Database\Factories;

use App\Models\PromoCode;
use App\Models\PromotionRule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PromoCode>
 */
class PromoCodeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(Str::random(8)),
            'promotion_rule_id' => PromotionRule::factory(),
            'usage_limit' => null,
            'usage_limit_per_email' => 1,
            'usage_count' => 0,
            'valid_from' => null,
            'valid_until' => null,
            'is_active' => true,
            'issued_to_email' => null,
            'metadata' => null,
            'event_id' => null,
        ];
    }

    public function singleUse(): static
    {
        return $this->state(fn () => ['usage_limit' => 1]);
    }

    public function unlimited(): static
    {
        return $this->state(fn () => [
            'usage_limit' => null,
            'usage_limit_per_email' => null,
        ]);
    }
}
