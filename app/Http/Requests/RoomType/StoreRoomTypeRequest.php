<?php

namespace App\Http\Requests\RoomType;

use Illuminate\Foundation\Http\FormRequest;

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
}
