<?php

namespace App\Http\Requests\Allotment;

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
            'event_id' => ['required', 'exists:events,id'],
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
            'event_id.required' => 'Event is required for allotment.',
            'end_date.after_or_equal' => 'End date must be on or after start date.',
        ];
    }
}
