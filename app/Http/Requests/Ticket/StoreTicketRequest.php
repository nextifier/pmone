<?php

namespace App\Http\Requests\Ticket;

use App\Enums\Ticketing\PurchaseType;
use App\Enums\Ticketing\TicketKind;
use App\Enums\Ticketing\TicketVisibility;
use App\Models\EventDay;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tickets.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', new Enum(TicketKind::class)],
            'title' => ['required', 'array'],
            'title.en' => ['required', 'string', 'max:255'],
            'title.id' => ['nullable', 'string', 'max:255'],
            'title.ja' => ['nullable', 'string', 'max:255'],
            'title.ko' => ['nullable', 'string', 'max:255'],
            'title.zh' => ['nullable', 'string', 'max:255'],

            'tier' => ['nullable', 'string', 'max:255'],
            'benefits' => ['nullable', 'array'],
            'benefits.*' => ['string', 'max:255'],
            'currency' => ['sometimes', 'string', 'size:3'],

            'purchase_type' => ['required', new Enum(PurchaseType::class)],
            'external_url' => ['nullable', 'url', 'max:1000', Rule::requiredIf($this->input('purchase_type') === PurchaseType::External->value)],

            'more_details' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],

            'print_on_redeem' => ['sometimes', 'boolean'],
            'requires_day_selection' => ['sometimes', 'boolean'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'min_quantity' => ['sometimes', 'integer', 'min:1'],
            'max_quantity' => ['nullable', 'integer', 'min:1', 'gte:min_quantity'],

            'valid_days' => ['nullable', 'array'],
            'valid_days.*' => ['integer', 'exists:event_days,id'],

            'is_active' => ['sometimes', 'boolean'],
            'visibility' => ['sometimes', new Enum(TicketVisibility::class)],

            'tmp_poster' => ['nullable', 'string'],
            'delete_poster' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.en.required' => 'Title (English) is required.',
            'external_url.required' => 'External URL is required for external tickets.',
            'max_quantity.gte' => 'Max quantity must be greater than or equal to min quantity.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->validateValidDays($validator);
        });
    }

    protected function validateValidDays(Validator $validator): void
    {
        $dayIds = array_filter((array) $this->input('valid_days', []));

        if (empty($dayIds)) {
            return;
        }

        $kind = $this->resolveKind();

        if ($kind !== TicketKind::Entry->value) {
            $validator->errors()->add('valid_days', 'Valid days can only be set on entry tickets.');

            return;
        }

        $event = $this->route('event');

        if (! $event) {
            return;
        }

        $foreignDayExists = EventDay::query()
            ->whereIn('id', $dayIds)
            ->where('event_id', '!=', $event->id)
            ->exists();

        if ($foreignDayExists) {
            $validator->errors()->add('valid_days', 'Valid days must belong to this event.');
        }
    }

    protected function resolveKind(): ?string
    {
        return $this->input('kind');
    }
}
