<?php

namespace App\Http\Requests\Allotment;

use App\Models\HotelEventAllotment;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreAllotmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('allotments.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'room_type_id' => ['required', 'exists:room_types,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'release_at' => ['nullable', 'date'],
            'surcharge_type' => ['nullable', 'string', 'in:fixed,percentage'],
            'surcharge_amount' => ['nullable', 'numeric', 'min:0', 'required_with:surcharge_type'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'End date must be on or after start date.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $hotel = $this->route('hotel');
            if (! $hotel) {
                return;
            }

            $overlap = HotelEventAllotment::query()
                ->where('hotel_id', $hotel->id)
                ->where('room_type_id', $this->input('room_type_id'))
                ->where('start_date', '<=', $this->input('end_date'))
                ->where('end_date', '>=', $this->input('start_date'))
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
