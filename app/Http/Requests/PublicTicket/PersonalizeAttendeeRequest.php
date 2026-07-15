<?php

namespace App\Http\Requests\PublicTicket;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Email;

class PersonalizeAttendeeRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', Email::default(), 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            // Registration answers keyed by field ulid; per-type validation
            // happens against the event's field catalog in the controller.
            'registration' => ['sometimes', 'array'],
        ];
    }
}
