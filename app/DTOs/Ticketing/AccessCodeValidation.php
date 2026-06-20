<?php

namespace App\DTOs\Ticketing;

use App\Enums\Ticketing\AccessCodePriceEffect;
use App\Models\AccessCode;

/**
 * Immutable result of an access code validation attempt. The public toArray()
 * deliberately omits internals (used_count/max_uses/bind_*) so a brute-forced
 * code never leaks quota or binding details (§8).
 */
final readonly class AccessCodeValidation
{
    public const ERROR_INVALID_CODE = 'INVALID_CODE';

    public const ERROR_REVOKED = 'REVOKED';

    public const ERROR_NOT_YET_VALID = 'NOT_YET_VALID';

    public const ERROR_EXPIRED = 'EXPIRED';

    public const ERROR_USAGE_LIMIT_REACHED = 'USAGE_LIMIT_REACHED';

    public const ERROR_BIND_EMAIL_MISMATCH = 'BIND_EMAIL_MISMATCH';

    public const ERROR_BIND_PHONE_MISMATCH = 'BIND_PHONE_MISMATCH';

    public const ERROR_TICKET_NOT_UNLOCKED = 'TICKET_NOT_UNLOCKED';

    public const ERROR_QTY_EXCEEDS_REDEMPTION_LIMIT = 'QTY_EXCEEDS_REDEMPTION_LIMIT';

    public const ERROR_STACKING_NOT_ALLOWED = 'STACKING_NOT_ALLOWED';

    public const ERROR_WRONG_EVENT = 'WRONG_EVENT';

    /**
     * @param  array<int, array{ticket_id: int, slug: string, title: string|null}>  $unlocks
     */
    public function __construct(
        public bool $valid,
        public ?AccessCode $code = null,
        public ?string $errorCode = null,
        public ?string $message = null,
        public array $unlocks = [],
        public ?AccessCodePriceEffect $priceEffect = null,
        public ?float $priceValue = null,
        public ?float $previewDiscount = null,
        public bool $stackable = false,
    ) {}

    /**
     * @param  array<int, array{ticket_id: int, slug: string, title: string|null}>  $unlocks
     */
    public static function ok(
        AccessCode $code,
        array $unlocks = [],
        ?float $previewDiscount = null,
    ): self {
        return new self(
            valid: true,
            code: $code,
            unlocks: $unlocks,
            priceEffect: $code->price_effect,
            priceValue: $code->price_value !== null ? (float) $code->price_value : null,
            previewDiscount: $previewDiscount,
            stackable: (bool) $code->stackable,
        );
    }

    public static function fail(string $errorCode, string $message, ?AccessCode $code = null): self
    {
        return new self(
            valid: false,
            code: $code,
            errorCode: $errorCode,
            message: $message,
        );
    }

    /**
     * Public-safe shape. Reveals only which tickets the code unlocks plus the
     * price-effect preview — never quota, binding, or status internals.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'error_code' => $this->errorCode,
            'message' => $this->message,
            'code' => $this->valid && $this->code ? $this->code->code : null,
            'unlocks' => $this->unlocks,
            'price_effect' => $this->priceEffect?->value,
            'price_value' => $this->priceValue,
            'preview_discount' => $this->previewDiscount,
            'stackable' => $this->stackable,
        ];
    }
}
