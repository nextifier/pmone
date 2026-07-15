<?php

namespace App\Http\Requests\Attendee;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Email;

class UpdateEventAttendeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('attendees.update') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', Email::default(), 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'selected_event_day_id' => ['sometimes', 'nullable', 'integer', 'exists:event_days,id'],
            'ticket_session_id' => ['sometimes', 'nullable', 'integer', 'exists:ticket_sessions,id'],
            'checked_in' => ['sometimes', 'boolean'],
            // Registration answers keyed by field ulid; per-type validation
            // happens against the event's field catalog in AttendeeService.
            'registration' => ['sometimes', 'array'],
        ];
    }
}
