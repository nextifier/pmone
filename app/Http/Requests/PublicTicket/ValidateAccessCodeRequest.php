<?php

namespace App\Http\Requests\PublicTicket;

use Illuminate\Foundation\Http\FormRequest;

class ValidateAccessCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'code' => ['required', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'items' => ['nullable', 'array'],
            'items.*.ticket_id' => ['required_with:items', 'integer'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
