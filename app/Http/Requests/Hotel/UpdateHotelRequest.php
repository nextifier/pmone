<?php

namespace App\Http\Requests\Hotel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Email;

class UpdateHotelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hotels.update') ?? false;
    }

    public function rules(): array
    {
        $hotelId = $this->route('hotel')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'alpha_dash',
                Rule::unique('hotels', 'slug')
                    ->ignore($hotelId)
                    ->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:10000'],
            'star_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'address' => ['nullable', 'array'],
            'address.street' => ['nullable', 'string', 'max:500'],
            'address.city' => ['nullable', 'string', 'max:255'],
            'address.province' => ['nullable', 'string', 'max:255'],
            'address.country' => ['nullable', 'string', 'max:255'],
            'google_maps_link' => ['nullable', 'url', 'max:500'],
            'google_maps_embed_src' => ['nullable', 'string', 'max:2000'],
            'facilities' => ['nullable', 'array'],
            'facilities.*' => ['string', 'max:100'],
            'contact_email' => ['nullable', Email::default(), 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'cancellation_policy' => ['nullable', 'string', 'max:5000'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'service_charge_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'settings' => ['nullable', 'array'],
            'more_details' => ['nullable', 'array'],
            'tmp_featured' => ['nullable', 'string'],
            'delete_featured' => ['nullable', 'boolean'],
            'gallery_files' => ['nullable', 'array'],
            'gallery_files.*' => ['string'],

            // Pivot fields when updating event-scoped attachment
            'pivot' => ['nullable', 'array'],
            'pivot.is_active' => ['nullable', 'boolean'],
            'pivot.notes' => ['nullable', 'string', 'max:2000'],
            'pivot.order_column' => ['nullable', 'integer'],
        ];
    }
}
