<?php

namespace App\DTOs\Promotion;

use App\Models\PromoCode;
use App\Models\PromotionRule;

/**
 * Immutable result of a promo code validation attempt.
 */
final readonly class PromoCodeValidation
{
    public const ERROR_INVALID_CODE = 'INVALID_CODE';

    public const ERROR_INACTIVE = 'INACTIVE';

    public const ERROR_NOT_YET_VALID = 'NOT_YET_VALID';

    public const ERROR_EXPIRED = 'EXPIRED';

    public const ERROR_USAGE_LIMIT_REACHED = 'USAGE_LIMIT_REACHED';

    public const ERROR_ALREADY_USED = 'ALREADY_USED';

    public const ERROR_NOT_ELIGIBLE = 'NOT_ELIGIBLE';

    public const ERROR_NOT_APPLICABLE_TO_PURCHASE_TYPE = 'NOT_APPLICABLE_TO_PURCHASE_TYPE';

    public const ERROR_DOES_NOT_APPLY = 'DOES_NOT_APPLY';

    public const ERROR_MIN_PURCHASE_NOT_MET = 'MIN_PURCHASE_NOT_MET';

    public const ERROR_STACKING_NOT_ALLOWED = 'STACKING_NOT_ALLOWED';

    /**
     * @param  array<int, array{item_id: int|null, label: string, bonus_qty: int, unit_price: float}>|null  $bonusItems
     */
    public function __construct(
        public bool $valid,
        public ?PromotionRule $rule = null,
        public ?PromoCode $code = null,
        public ?string $errorCode = null,
        public ?string $message = null,
        public ?float $previewDiscount = null,
        public ?float $previewTotal = null,
        public ?array $bonusItems = null,
    ) {}

    /**
     * @param  array<int, array{item_id: int|null, label: string, bonus_qty: int, unit_price: float}>|null  $bonusItems
     */
    public static function ok(
        PromotionRule $rule,
        PromoCode $code,
        ?float $previewDiscount = null,
        ?float $previewTotal = null,
        ?array $bonusItems = null,
    ): self {
        return new self(
            valid: true,
            rule: $rule,
            code: $code,
            previewDiscount: $previewDiscount,
            previewTotal: $previewTotal,
            bonusItems: $bonusItems,
        );
    }

    public static function fail(string $errorCode, string $message, ?PromoCode $code = null, ?PromotionRule $rule = null): self
    {
        return new self(
            valid: false,
            rule: $rule,
            code: $code,
            errorCode: $errorCode,
            message: $message,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'error_code' => $this->errorCode,
            'message' => $this->message,
            'preview_discount' => $this->previewDiscount,
            'preview_total' => $this->previewTotal,
            'bonus_items' => $this->bonusItems,
            'rule' => $this->rule ? [
                'ulid' => $this->rule->ulid,
                'name' => $this->rule->name,
                'kind' => $this->rule->kind?->value,
                'value_type' => $this->rule->value_type?->value,
                'value' => (float) $this->rule->value,
                'max_discount_amount' => $this->rule->max_discount_amount !== null ? (float) $this->rule->max_discount_amount : null,
            ] : null,
            'code' => $this->code ? [
                'code' => $this->code->code,
                'usage_count' => $this->code->usage_count,
                'usage_limit' => $this->code->usage_limit,
            ] : null,
        ];
    }
}
