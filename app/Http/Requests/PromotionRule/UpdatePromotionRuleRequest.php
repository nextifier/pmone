<?php

namespace App\Http\Requests\PromotionRule;

use App\Enums\AdjustmentKind;
use App\Enums\AdjustmentValueType;
use App\Enums\PenaltyTriggerType;
use App\Enums\StackingMode;
use App\Services\Promotion\ApplicabilityChecker;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdatePromotionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('promotion_rules.update') ?? false;
    }

    public function rules(): array
    {
        $ruleId = $this->route('rule')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes', 'required', 'string', 'max:120',
                Rule::unique('promotion_rules', 'slug')->ignore($ruleId),
                'regex:/^[a-z0-9-]+$/',
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'kind' => ['sometimes', 'required', new Enum(AdjustmentKind::class)],
            'value_type' => ['sometimes', 'required', new Enum(AdjustmentValueType::class)],
            'value' => ['sometimes', 'required', 'numeric', 'min:0'],
            'value_config' => ['nullable', 'array'],
            'value_config.buy_qty' => ['nullable', 'integer', 'min:1'],
            'value_config.get_free_qty' => ['nullable', 'integer', 'min:1'],
            'value_config.bundle_qty' => ['nullable', 'integer', 'min:1'],
            'value_config.bundle_price' => ['nullable', 'numeric', 'min:0'],
            'value_config.max_free_qty' => ['nullable', 'integer', 'min:1'],
            'value_config.metric' => ['nullable', 'string', 'in:qty,amount'],
            'value_config.tiers' => ['nullable', 'array'],
            'value_config.tiers.*' => ['array:min,value'],
            'value_config.tiers.*.min' => ['required_with:value_config.tiers.*', 'numeric', 'min:0'],
            'value_config.tiers.*.value' => ['required_with:value_config.tiers.*', 'numeric', 'min:0'],
            'value_config.target_line_keys' => ['nullable', 'array'],
            'value_config.target_line_keys.*' => ['string', 'in:rooms,transfer,surcharge,subtotal'],
            'value_config.transfer_option_ids' => ['nullable', 'array'],
            'value_config.transfer_option_ids.*' => ['integer'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'min_purchase_amount' => ['nullable', 'numeric', 'min:0'],
            'applies_before_tax' => ['boolean'],
            'stacking_mode' => ['sometimes', 'required', new Enum(StackingMode::class)],
            'priority' => ['nullable', 'integer', 'min:0', 'max:32000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['boolean'],
            'target_types' => ['nullable', 'array'],
            'target_types.*' => ['string', 'max:100'],
            'applicability' => ['nullable', 'array'],
            'trigger_type' => ['sometimes', 'required', new Enum(PenaltyTriggerType::class)],
            'trigger_config' => ['nullable', 'array'],
            'revert_usage_on_cancel' => ['boolean'],
            'event_id' => ['nullable', 'exists:events,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $existing = $this->route('rule');

            $kind = $this->input('kind', $existing?->kind?->value);
            $valueType = $this->input('value_type', $existing?->value_type?->value);
            $value = $this->has('value')
                ? (float) $this->input('value')
                : (float) ($existing?->value ?? 0);
            $config = $this->has('value_config')
                ? $this->input('value_config')
                : ($existing?->value_config ?? null);

            if ($valueType === 'percentage' && $value > 100 && $kind === 'discount') {
                $validator->errors()->add('value', 'Percentage discount cannot exceed 100%.');
            }

            // Penalty kind only supports flat value_types — same restriction as
            // StorePromotionRuleRequest. Mirror here so admins can't flip kind
            // to penalty on a buy_x_get_y rule without also changing value_type.
            $conditional = ['buy_x_get_y', 'bundle_price', 'tiered_percentage', 'tiered_fixed_amount', 'free_addon'];
            if ($kind === 'penalty' && in_array($valueType, $conditional, true)) {
                $validator->errors()->add('value_type', 'Penalty rules only support percentage or fixed_amount.');
            }

            if ($valueType !== null) {
                $this->validateValueConfig($validator, $valueType, $config);
            }

            $applicability = $this->input('applicability');
            if (is_array($applicability)) {
                $unknown = ApplicabilityChecker::unknownKeys($applicability);
                if (! empty($unknown)) {
                    $validator->errors()->add('applicability', 'Unknown applicability key(s): '.implode(', ', $unknown));
                }
            }
        });
    }

    protected function validateValueConfig(Validator $validator, mixed $valueType, mixed $config): void
    {
        $requiresConfig = match ($valueType) {
            'buy_x_get_y' => ['buy_qty', 'get_free_qty'],
            'bundle_price' => ['bundle_qty', 'bundle_price'],
            'tiered_percentage', 'tiered_fixed_amount' => ['tiers'],
            'free_addon' => [],
            default => null,
        };

        if ($requiresConfig === null) {
            return;
        }

        if (! is_array($config)) {
            $validator->errors()->add('value_config', "value_config is required for value_type {$valueType}.");

            return;
        }

        foreach ($requiresConfig as $key) {
            if (! array_key_exists($key, $config) || $config[$key] === null || $config[$key] === '') {
                $validator->errors()->add("value_config.{$key}", "Field {$key} is required for value_type {$valueType}.");
            }
        }

        if (in_array($valueType, ['tiered_percentage', 'tiered_fixed_amount'], true)) {
            $tiers = $config['tiers'] ?? [];
            if (! is_array($tiers) || empty($tiers)) {
                $validator->errors()->add('value_config.tiers', 'At least one tier is required.');
            }

            if ($valueType === 'tiered_percentage') {
                foreach ($tiers as $i => $tier) {
                    if (isset($tier['value']) && (float) $tier['value'] > 100) {
                        $validator->errors()->add("value_config.tiers.{$i}.value", 'Tier percentage cannot exceed 100%.');
                    }
                }
            }
        }
    }
}
