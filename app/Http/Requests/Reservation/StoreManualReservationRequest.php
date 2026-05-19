<?php

namespace App\Http\Requests\Reservation;

use App\Enums\IdentityType;
use App\Enums\TransferDirection;
use App\Models\HotelTransferOption;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreManualReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reservations.manual_entry') ?? false;
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
            'notes' => ['nullable', 'string', 'max:5000'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.room_type_id' => ['required', 'exists:room_types,id'],
            'items.*.check_in_date' => ['required', 'date'],
            'items.*.check_out_date' => ['required', 'date', 'after:items.*.check_in_date'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:20'],
            'items.*.guest_name' => ['nullable', 'string', 'max:255'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],

            'transfers' => ['nullable', 'array'],
            'transfers.*.transfer_option_id' => ['required_with:transfers', 'exists:hotel_transfer_options,id'],
            'transfers.*.direction' => ['required_with:transfers', new Enum(TransferDirection::class)],
            'transfers.*.transfer_date' => ['required_with:transfers', 'date'],
            'transfers.*.pax_count' => ['required_with:transfers', 'integer', 'min:1'],
            'transfers.*.note' => ['nullable', 'string', 'max:1000'],
            // Price resolved server-side from HotelTransferOption (C1).

            'payment_mode' => ['required', 'string', 'in:skip,manual_paid,xendit'],

            'promo_code' => ['nullable', 'string', 'max:60'],
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
