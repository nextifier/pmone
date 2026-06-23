<?php

namespace App\Http\Requests\Adjustment;

use App\Enums\AdjustmentKind;
use App\Enums\AdjustmentValueType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Shared form request for applying an adjustment to either a Reservation or Order.
 *
 * Three modes (mutually exclusive):
 *  - promo_code: customer-facing flow, references a PromoCode
 *  - promotion_rule_id: admin selects an existing rule (with optional override_value)
 *  - kind + value_type + value + reason: ad-hoc admin manual entry
 */
class StoreAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('promotions.apply_manual') ?? false;
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', 'string', 'in:promo_code,promotion_rule,manual'],

            'promo_code' => ['required_if:mode,promo_code', 'string', 'max:60'],
            'email' => ['required_if:mode,promo_code', 'email', 'max:255'],

            'promotion_rule_id' => ['required_if:mode,promotion_rule', 'exists:promotion_rules,id'],
            'override_value' => ['nullable', 'numeric', 'min:0'],

            'kind' => ['required_if:mode,manual', new Enum(AdjustmentKind::class)],
            'value_type' => ['required_if:mode,manual', new Enum(AdjustmentValueType::class)],
            'value' => ['required_if:mode,manual', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:500'],

            // Optional: scope a manual adjustment to a single order item.
            // Belongs-to-order check is enforced in the controller.
            'order_item_id' => ['nullable', 'integer', 'exists:order_items,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $mode = $this->input('mode');
            $valueType = $this->input('value_type');
            $value = (float) $this->input('value', 0);
            $kind = $this->input('kind');

            if ($mode === 'manual' && $valueType === 'percentage' && $kind === 'discount' && $value > 100) {
                $validator->errors()->add('value', 'Percentage discount cannot exceed 100%.');
            }

            // Manual ad-hoc adjustments only support flat value_types because the
            // controller does not persist value_config. Conditional types (BOGO,
            // bundle, tiered, free_addon) must go through an existing rule via
            // mode=promotion_rule.
            if ($mode === 'manual' && ! in_array($valueType, ['percentage', 'fixed_amount'], true)) {
                $validator->errors()->add('value_type', 'Manual adjustments only support percentage or fixed_amount. Use mode=promotion_rule for other types.');
            }
        });
    }
}
