<?php

namespace Database\Factories;

use App\Enums\Ticketing\AccessCodeKind;
use App\Enums\Ticketing\AccessCodePriceEffect;
use App\Enums\Ticketing\AccessCodeStatus;
use App\Models\AccessCode;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AccessCode>
 */
class AccessCodeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(Str::random(10)),
            'kind' => AccessCodeKind::Shared,
            'event_id' => Event::factory(),
            'batch_id' => null,
            'max_uses' => null,
            'used_count' => 0,
            'valid_from' => null,
            'valid_until' => null,
            'bind_email' => null,
            'bind_phone' => null,
            'price_effect' => AccessCodePriceEffect::None,
            'price_value' => null,
            'stackable' => false,
            'max_qty_per_redemption' => 1,
            'status' => AccessCodeStatus::Active,
            'metadata' => null,
        ];
    }

    public function shared(?int $maxUses = 100): static
    {
        return $this->state(fn () => [
            'kind' => AccessCodeKind::Shared,
            'max_uses' => $maxUses,
        ]);
    }

    public function invitation(?string $email = null): static
    {
        return $this->state(fn () => [
            'kind' => AccessCodeKind::Invitation,
            'max_uses' => 1,
            'bind_email' => $email,
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['status' => AccessCodeStatus::Revoked]);
    }

    public function setPrice(float $value = 0): static
    {
        return $this->state(fn () => [
            'price_effect' => AccessCodePriceEffect::SetPrice,
            'price_value' => $value,
        ]);
    }

    public function percentageOff(float $percent): static
    {
        return $this->state(fn () => [
            'price_effect' => AccessCodePriceEffect::Percentage,
            'price_value' => $percent,
        ]);
    }

    public function amountOff(float $amount): static
    {
        return $this->state(fn () => [
            'price_effect' => AccessCodePriceEffect::Amount,
            'price_value' => $amount,
        ]);
    }

    public function stackable(): static
    {
        return $this->state(fn () => ['stackable' => true]);
    }
}
