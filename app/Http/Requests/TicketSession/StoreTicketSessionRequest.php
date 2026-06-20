<?php

namespace App\Http\Requests\TicketSession;

use App\Enums\Ticketing\TicketKind;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class StoreTicketSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('ticket_sessions.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'host' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ends_at.after_or_equal' => 'End time must be on or after start time.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->validateTicketIsAddOn($validator);
            $this->validateWithinEventRange($validator);
        });
    }

    protected function validateTicketIsAddOn(Validator $validator): void
    {
        $ticket = $this->route('ticket');

        if ($ticket && $ticket->kind !== TicketKind::AddOn) {
            $validator->errors()->add('label', 'Sessions can only be added to add-on tickets.');
        }
    }

    protected function validateWithinEventRange(Validator $validator): void
    {
        $event = $this->route('event');

        if (! $event || ! $event->start_date || ! $event->end_date) {
            return;
        }

        $eventStart = Carbon::parse($event->start_date)->startOfDay();
        $eventEnd = Carbon::parse($event->end_date)->endOfDay();

        foreach (['starts_at', 'ends_at'] as $field) {
            $value = $this->input($field);

            if ($value === null) {
                continue;
            }

            $moment = Carbon::parse($value);

            if ($moment->lt($eventStart) || $moment->gt($eventEnd)) {
                $validator->errors()->add($field, 'Session must fall within the event date range.');
            }
        }
    }
}
