<?php

namespace App\Http\Requests\PublicReservation;

use App\Enums\IdentityType;
use App\Enums\TransferDirection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StorePublicReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hotel_id' => ['required', 'exists:hotels,id'],

            'guest_name' => ['required', 'string', 'max:255'],
            'guest_email' => ['required', 'email', 'max:255'],
            'guest_phone' => ['required', 'string', 'max:50'],
            'guest_identity_type' => ['required', new Enum(IdentityType::class)],
            'guest_identity_number' => ['required', 'string', 'max:100'],
            'guest_nationality' => ['nullable', 'string', 'max:100'],
            'guest_company' => ['nullable', 'string', 'max:255'],
            'special_request' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.room_type_id' => ['required', 'exists:room_types,id'],
            'items.*.check_in_date' => ['required', 'date'],
            'items.*.check_out_date' => ['required', 'date', 'after:items.*.check_in_date'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:20'],
            'items.*.guest_name' => ['nullable', 'string', 'max:255'],
            'items.*.guest_identity' => ['nullable', 'string', 'max:100'],

            'transfers' => ['nullable', 'array'],
            'transfers.*.transfer_option_id' => ['required_with:transfers', 'exists:hotel_transfer_options,id'],
            'transfers.*.direction' => ['required_with:transfers', new Enum(TransferDirection::class)],
            'transfers.*.transfer_date' => ['required_with:transfers', 'date'],
            'transfers.*.transfer_time' => ['nullable', 'date_format:H:i'],
            'transfers.*.pickup_location' => ['nullable', 'string', 'max:500'],
            'transfers.*.dropoff_location' => ['nullable', 'string', 'max:500'],
            'transfers.*.flight_number' => ['nullable', 'string', 'max:50'],
            'transfers.*.flight_time' => ['nullable', 'date_format:H:i'],
            'transfers.*.pax_count' => ['required_with:transfers', 'integer', 'min:1'],
            'transfers.*.luggage_count' => ['nullable', 'integer', 'min:0'],
            'transfers.*.note' => ['nullable', 'string', 'max:1000'],
            'transfers.*.price' => ['required_with:transfers', 'numeric', 'min:0'],

            'accept_terms' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'accept_terms.accepted' => 'You must accept the terms and conditions.',
            'items.required' => 'At least one room must be selected.',
        ];
    }
}
