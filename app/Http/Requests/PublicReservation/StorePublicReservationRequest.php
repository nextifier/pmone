<?php

namespace App\Http\Requests\PublicReservation;

use App\Enums\IdentityType;
use App\Enums\TransferDirection;
use App\Models\HotelTransferOption;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Email;
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
            'event_id' => ['required', 'exists:events,id'],

            'guest_name' => ['required', 'string', 'max:255'],
            'guest_email' => ['required', Email::default(), 'max:255'],
            'guest_phone' => ['required', 'string', 'max:50'],
            'guest_identity_type' => ['required', new Enum(IdentityType::class)],
            'guest_identity_number' => ['required', 'string', 'max:100'],
            'guest_nationality' => ['nullable', 'string', 'max:100'],
            'guest_company' => ['nullable', 'string', 'max:255'],
            'special_request' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.room_type_id' => ['required', 'exists:room_types,id'],
            'items.*.check_in_date' => ['required', 'date', 'after_or_equal:today'],
            'items.*.check_out_date' => ['required', 'date', 'after:items.*.check_in_date'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:20'],
            'items.*.guest_name' => ['nullable', 'string', 'max:255'],
            'items.*.guest_identity' => ['nullable', 'string', 'max:100'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],

            'transfers' => ['nullable', 'array'],
            'transfers.*.transfer_option_id' => ['required_with:transfers', 'exists:hotel_transfer_options,id'],
            'transfers.*.direction' => ['required_with:transfers', new Enum(TransferDirection::class)],
            'transfers.*.transfer_date' => ['required_with:transfers', 'date', 'after_or_equal:today'],
            'transfers.*.transfer_time' => ['nullable', 'date_format:H:i'],
            'transfers.*.pickup_location' => ['nullable', 'string', 'max:500'],
            'transfers.*.dropoff_location' => ['nullable', 'string', 'max:500'],
            'transfers.*.flight_number' => ['nullable', 'string', 'max:50'],
            'transfers.*.flight_time' => ['nullable', 'date_format:H:i'],
            'transfers.*.pax_count' => ['required_with:transfers', 'integer', 'min:1'],
            'transfers.*.luggage_count' => ['nullable', 'integer', 'min:0'],
            'transfers.*.note' => ['nullable', 'string', 'max:1000'],
            // NOTE: `transfers.*.price` deliberately not validated/accepted from client.
            // Server resolves price from HotelTransferOption to prevent tampering (C1).

            'accept_terms' => ['accepted'],

            'promo_code' => ['nullable', 'string', 'max:60'],

            // Originating site URL (e.g. https://iicc.askindo.id), set server-side by
            // the booking proxy from its own siteUrl. Used to redirect the guest back
            // to the originating domain after payment. Validated against an allowlist
            // in ReservationService; an untrusted value falls back to FRONTEND_URL.
            'origin' => ['nullable', 'url', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'accept_terms.accepted' => 'You must accept the terms and conditions.',
            'items.required' => 'At least one room must be selected.',
            'items.*.check_in_date.after_or_equal' => 'Check-in date cannot be in the past.',
            'transfers.*.transfer_date.after_or_equal' => 'Transfer date cannot be in the past.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $hotelId = $this->input('hotel_id');
            $transfers = $this->input('transfers', []) ?? [];

            if (empty($transfers)) {
                return;
            }

            foreach ($transfers as $index => $transfer) {
                $option = HotelTransferOption::query()
                    ->where('id', $transfer['transfer_option_id'] ?? null)
                    ->where('hotel_id', $hotelId)
                    ->first();

                if (! $option) {
                    $validator->errors()->add(
                        "transfers.{$index}.transfer_option_id",
                        'Selected transfer option does not belong to this hotel.'
                    );

                    continue;
                }

                if (! $option->is_active) {
                    $validator->errors()->add(
                        "transfers.{$index}.transfer_option_id",
                        'Selected transfer option is no longer available.'
                    );

                    continue;
                }

                $paxCount = (int) ($transfer['pax_count'] ?? 0);
                if ($option->max_pax && $paxCount > $option->max_pax) {
                    $validator->errors()->add(
                        "transfers.{$index}.pax_count",
                        "Passenger count exceeds max capacity ({$option->max_pax}) for this transfer option."
                    );
                }
            }
        });
    }
}
