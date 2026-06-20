<?php

namespace App\Http\Requests\AccessCode;

use App\Enums\Ticketing\AccessCodePriceEffect;
use App\Enums\Ticketing\AccessCodeStatus;
use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccessCodeRequest extends FormRequest
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
            'status' => ['sometimes', Rule::in(AccessCodeStatus::values())],
            'max_uses' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'valid_from' => ['sometimes', 'nullable', 'date'],
            'valid_until' => ['sometimes', 'nullable', 'date', 'after_or_equal:valid_from'],
            'bind_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'bind_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'price_effect' => ['sometimes', Rule::in(AccessCodePriceEffect::values())],
            'price_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'stackable' => ['sometimes', 'boolean'],
            'max_qty_per_redemption' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'unlocks' => ['sometimes', 'array'],
            'unlocks.*' => ['integer', $this->ticketBelongsToEvent()],
        ];
    }

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
}
