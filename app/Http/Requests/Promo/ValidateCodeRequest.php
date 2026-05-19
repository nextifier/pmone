<?php

namespace App\Http\Requests\Promo;

use Illuminate\Foundation\Http\FormRequest;

class ValidateCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:255'],
            'target_type' => ['required', 'string', 'in:Reservation,Order'],

            // Payload mirrors the shape of the eventual reservation/order create body,
            // used to build a transient entity for validation preview.
            'payload' => ['required', 'array'],

            // Reservation-specific fields used to build transient entity
            'payload.hotel_id' => ['required_if:target_type,Reservation', 'exists:hotels,id'],
            'payload.event_id' => ['nullable', 'exists:events,id'],
            'payload.items' => ['required_if:target_type,Reservation', 'array', 'min:1'],
            'payload.items.*.room_type_id' => ['required', 'exists:room_types,id'],
            'payload.items.*.check_in_date' => ['required', 'date'],
            'payload.items.*.check_out_date' => ['required', 'date', 'after:payload.items.*.check_in_date'],
            'payload.items.*.qty' => ['required', 'integer', 'min:1'],
            'payload.transfers' => ['nullable', 'array'],
            'payload.transfers.*.transfer_option_id' => ['required_with:payload.transfers', 'exists:hotel_transfer_options,id'],
        ];
    }
}
