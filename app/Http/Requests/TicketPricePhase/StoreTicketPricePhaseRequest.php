<?php

namespace App\Http\Requests\TicketPricePhase;

use App\Models\TicketPricePhase;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreTicketPricePhaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('ticket_price_phases.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'quota' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ends_at.after_or_equal' => 'End date must be on or after start date.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $ticket = $this->route('ticket');

            if (! $ticket) {
                return;
            }

            $startsAt = $this->input('starts_at');
            $endsAt = $this->input('ends_at');

            // Two ranges overlap when each starts before (or when) the other ends.
            // Null bounds are open-ended: a null start is -infinity, a null end is
            // +infinity, so the corresponding boundary check is skipped.
            $query = TicketPricePhase::query()
                ->where('ticket_id', $ticket->id)
                ->when($this->route('pricePhase'), fn ($q, $phase) => $q->where('id', '!=', $phase->id))
                ->when($endsAt !== null, fn ($q) => $q->where(function ($q) use ($endsAt) {
                    $q->whereNull('starts_at')->orWhere('starts_at', '<=', $endsAt);
                }))
                ->when($startsAt !== null, fn ($q) => $q->where(function ($q) use ($startsAt) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>=', $startsAt);
                }));

            if ($query->exists()) {
                $validator->errors()->add(
                    'starts_at',
                    'Price phase date range overlaps with an existing phase for this ticket.'
                );
            }
        });
    }
}
