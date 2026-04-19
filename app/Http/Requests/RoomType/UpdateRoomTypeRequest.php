<?php

namespace App\Http\Requests\RoomType;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoomTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('room_types.update') ?? false;
    }

    public function rules(): array
    {
        $hotelId = $this->route('hotel')?->id;
        $roomTypeId = $this->route('room_type')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('room_types', 'slug')
                    ->ignore($roomTypeId)
                    ->where(fn ($q) => $q->where('hotel_id', $hotelId)),
            ],
            'description' => ['nullable', 'string', 'max:10000'],
            'max_pax' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'bed_type' => ['nullable', 'string', 'max:100'],
            'view_type' => ['nullable', 'string', 'max:50'],
            'area_sqm' => ['nullable', 'numeric', 'min:0'],
            'base_rate' => ['sometimes', 'numeric', 'min:0'],
            'breakfast_included' => ['sometimes', 'boolean'],
            'smoking_allowed' => ['sometimes', 'boolean'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['string', 'max:100'],
            'cancellation_policy' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
            'gallery_files' => ['nullable', 'array'],
            'gallery_files.*' => ['string'],
        ];
    }
}
