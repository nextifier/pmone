/**
 * Human-readable mapping for promo code validation error codes.
 * Mirrors the error codes returned by /api/public/promo-codes/validate.
 */
export const usePromoCodeErrors = () => ({
  INVALID_CODE: "Promo code not found.",
  INACTIVE: "This promo code is no longer active.",
  NOT_YET_VALID: "This promo code is not yet valid.",
  EXPIRED: "This promo code has expired.",
  USAGE_LIMIT_REACHED: "This promo code has reached its usage limit.",
  ALREADY_USED: "You have already used this promo code.",
  NOT_ELIGIBLE: "This promo code is not eligible for your account.",
  NOT_APPLICABLE_TO_PURCHASE_TYPE: "This promo code does not apply to this purchase type.",
  DOES_NOT_APPLY: "This promo code does not apply to your booking.",
  MIN_PURCHASE_NOT_MET: "Minimum purchase amount not reached for this promo code.",
  STACKING_NOT_ALLOWED: "This promo code cannot be combined with current active promotions.",
} as Record<string, string>);
