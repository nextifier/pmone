<?php

namespace App\DTOs\Promotion;

final readonly class ApplicabilityResult
{
    public function __construct(
        public bool $passes,
        public ?string $reason = null,
    ) {}

    public static function pass(): self
    {
        return new self(passes: true);
    }

    public static function fail(string $reason): self
    {
        return new self(passes: false, reason: $reason);
    }
}
