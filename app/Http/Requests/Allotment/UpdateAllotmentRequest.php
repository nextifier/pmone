<?php

namespace App\Http\Requests\Allotment;

use App\Models\HotelEventAllotment;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAllotmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('allotments.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'room_type_id' => ['sometimes', 'exists:room_types,id'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'release_at' => ['nullable', 'date'],
            'surcharge_type' => ['nullable', 'string', 'in:fixed,percentage'],
            'surcharge_amount' => ['nullable', 'numeric', 'min:0', 'required_with:surcharge_type'],
            'base_rate_override' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $hotel = $this->route('hotel');
            $allotment = $this->route('allotment');

            if (! $hotel || ! $allotment) {
                return;
            }

            $roomTypeId = $this->input('room_type_id', $allotment->room_type_id);
            $startDate = $this->input('start_date', $allotment->start_date?->toDateString());
            $endDate = $this->input('end_date', $allotment->end_date?->toDateString());

            $overlap = HotelEventAllotment::query()
                ->where('hotel_id', $hotel->id)
                ->where('room_type_id', $roomTypeId)
                ->where('id', '!=', $allotment->id)
                ->where('start_date', '<=', $endDate)
                ->where('end_date', '>=', $startDate)
                ->exists();

            if ($overlap) {
                $validator->errors()->add(
                    'start_date',
                    'Allotment date range overlaps with an existing allotment for this room type.'
                );
            }
        });
    }
}
