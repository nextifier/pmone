<?php

namespace App\Services\Pricing;

/**
 * Immutable value object representing the result of a pricing calculation.
 */
final readonly class PricingResult
{
    /**
     * @param  array<int, array{key: string, amount: float, taxable: bool}>  $lines
     * @param  array<int, array{
     *     id: int|null,
     *     kind: string,
     *     label: string,
     *     value_type: string,
     *     value: float,
     *     base_amount: float,
     *     amount: float,
     *     promotion_rule_id: int|null,
     *     promo_code_id: int|null
     * }>  $adjustments
     */
    public function __construct(
        public float $subtotal,
        public float $taxableBase,
        public float $penaltyAmount,
        public float $discountAmount,
        public float $taxAmount,
        public float $serviceChargeAmount,
        public float $totalAmount,
        public array $lines = [],
        public array $adjustments = [],
    ) {}

    /**
     * Map to entity column updates.
     *
     * @return array<string, float>
     */
    public function toEntityColumns(): array
    {
        return [
            'penalty_amount' => $this->penaltyAmount,
            'discount_amount' => $this->discountAmount,
            'tax_amount' => $this->taxAmount,
            'service_charge_amount' => $this->serviceChargeAmount,
            'total_amount' => $this->totalAmount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'subtotal' => $this->subtotal,
            'taxable_base' => $this->taxableBase,
            'penalty_amount' => $this->penaltyAmount,
            'discount_amount' => $this->discountAmount,
            'tax_amount' => $this->taxAmount,
            'service_charge_amount' => $this->serviceChargeAmount,
            'total_amount' => $this->totalAmount,
            'lines' => $this->lines,
            'adjustments' => $this->adjustments,
        ];
    }
}
