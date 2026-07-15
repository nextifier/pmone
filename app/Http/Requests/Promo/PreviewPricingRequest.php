<?php

namespace App\Http\Requests\Promo;

use App\Enums\IdentityType;
use App\Enums\TransferDirection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Email;
use Illuminate\Validation\Rules\Enum;

class PreviewPricingRequest extends FormRequest
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
            'guest_email' => ['nullable', Email::default(), 'max:255'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.room_type_id' => ['required', 'exists:room_types,id'],
            'items.*.check_in_date' => ['required', 'date'],
            'items.*.check_out_date' => ['required', 'date', 'after:items.*.check_in_date'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:20'],

            'transfers' => ['nullable', 'array'],
            'transfers.*.transfer_option_id' => ['required_with:transfers', 'exists:hotel_transfer_options,id'],
            'transfers.*.direction' => ['required_with:transfers', new Enum(TransferDirection::class)],
            'transfers.*.pax_count' => ['required_with:transfers', 'integer', 'min:1'],

            'promo_code' => ['nullable', 'string', 'max:60'],

            // Optional identity fields for stricter applicability check (e.g. first_purchase_only)
            'guest_identity_type' => ['nullable', new Enum(IdentityType::class)],
        ];
    }
}
