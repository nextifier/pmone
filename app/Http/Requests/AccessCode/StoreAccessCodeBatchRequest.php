<?php

namespace App\Http\Requests\AccessCode;

use App\Enums\Ticketing\AccessCodeKind;
use App\Enums\Ticketing\AccessCodePriceEffect;
use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccessCodeBatchRequest extends FormRequest
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
            'kind' => ['required', Rule::in(AccessCodeKind::values())],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'price_effect' => ['nullable', Rule::in(AccessCodePriceEffect::values())],
            'price_value' => ['nullable', 'numeric', 'min:0', 'required_unless:price_effect,none,null'],
            'stackable' => ['sometimes', 'boolean'],
            'max_qty_per_redemption' => ['nullable', 'integer', 'min:1', 'max:50'],

            'unlocks' => ['required', 'array', 'min:1'],
            'unlocks.*' => ['integer', $this->ticketBelongsToEvent()],

            'assigned_to' => ['nullable', 'string', 'max:255'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'recipients' => ['nullable', 'array'],
            'recipients.*.email' => ['nullable', 'email', 'max:255'],
            'recipients.*.phone' => ['nullable', 'string', 'max:50'],
            'recipients.*.name' => ['nullable', 'string', 'max:255'],

            'prefix' => ['nullable', 'string', 'max:10'],
            'length' => ['nullable', 'integer', 'min:6', 'max:20'],

            'delivery' => ['nullable', Rule::in(['none', 'send_invites'])],
        ];
    }

    /**
     * Ensure each unlocked ticket belongs to the event in the route.
     */
    protected function ticketBelongsToEvent(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $event = $this->route('event');
            $eventId = is_object($event) ? $event->id : $event;

            $exists = Ticket::query()
                ->whereKey($value)
                ->where('event_id', $eventId)
                ->exists();

            if (! $exists) {
                $fail('The selected ticket does not belong to this event.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'unlocks.required' => 'Choose at least one ticket for this code to unlock.',
            'price_value.required_unless' => 'A price value is required when a price effect is set.',
        ];
    }
}
