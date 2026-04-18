<?php

namespace App\Http\Requests\Allotment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAllotmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('allotments.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'event_id' => ['sometimes', 'exists:events,id'],
            'room_type_id' => ['sometimes', 'exists:room_types,id'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'release_at' => ['nullable', 'date'],
            'surcharge_type' => ['nullable', 'string', 'in:fixed,percentage'],
            'surcharge_amount' => ['nullable', 'numeric', 'min:0', 'required_with:surcharge_type'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
