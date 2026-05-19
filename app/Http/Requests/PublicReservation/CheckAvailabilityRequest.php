<?php

namespace App\Http\Requests\PublicReservation;

use Illuminate\Foundation\Http\FormRequest;

class CheckAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hotel_id' => ['required', 'exists:hotels,id'],
            'event_slug' => ['required', 'string', 'exists:events,slug'],
            'room_type_id' => ['required', 'exists:room_types,id'],
            'check_in_date' => ['required', 'date'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'qty' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }
}
