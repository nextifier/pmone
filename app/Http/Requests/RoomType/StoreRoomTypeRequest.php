<?php

namespace App\Http\Requests\RoomType;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoomTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('room_types.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'max_pax' => ['required', 'integer', 'min:1', 'max:20'],
            'bed_type' => ['nullable', 'string', 'max:100'],
            'area_sqm' => ['nullable', 'numeric', 'min:0'],
            'base_rate' => ['required', 'numeric', 'min:0'],
            'pricing_type' => ['sometimes', 'string', Rule::in(['flat', 'dynamic'])],
            'pricing_periods' => ['sometimes', 'array'],
            'pricing_periods.*.id' => ['sometimes', 'nullable', 'integer', 'exists:room_type_pricing_periods,id'],
            'pricing_periods.*.start_date' => ['required_with:pricing_periods', 'date'],
            'pricing_periods.*.end_date' => ['required_with:pricing_periods', 'date', 'after_or_equal:pricing_periods.*.start_date'],
            'pricing_periods.*.rate' => ['required_with:pricing_periods', 'numeric', 'min:0'],
            'pricing_periods.*.label' => ['nullable', 'string', 'max:100'],
            'pricing_periods.*.is_active' => ['sometimes', 'boolean'],
            'breakfast_included' => ['sometimes', 'boolean'],
            'smoking_allowed' => ['sometimes', 'boolean'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['string', 'max:100'],
            'cancellation_policy' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
            'settings' => ['nullable', 'array'],
            'more_details' => ['nullable', 'array'],
            'gallery_files' => ['nullable', 'array'],
            'gallery_files.*' => ['string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Room type name is required.',
            'base_rate.required' => 'Base rate is required.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $pricingType = $this->input('pricing_type', 'flat');
            $periods = $this->input('pricing_periods', []);

            if ($pricingType === 'dynamic' && (! is_array($periods) || count($periods) === 0)) {
                $v->errors()->add('pricing_periods', 'Dynamic pricing requires at least one pricing period.');

                return;
            }

            if (! is_array($periods) || count($periods) < 2) {
                return;
            }

            $sorted = collect($periods)
                ->filter(fn ($p) => ! empty($p['start_date']) && ! empty($p['end_date']))
                ->sortBy('start_date')
                ->values();

            for ($i = 0; $i < $sorted->count() - 1; $i++) {
                $current = $sorted[$i];
                $next = $sorted[$i + 1];

                if ($current['end_date'] >= $next['start_date']) {
                    $v->errors()->add('pricing_periods', "Pricing periods overlap: {$current['start_date']} - {$current['end_date']} and {$next['start_date']} - {$next['end_date']}.");
                    break;
                }
            }
        });
    }
}
