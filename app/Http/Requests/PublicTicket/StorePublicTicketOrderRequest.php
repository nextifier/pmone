<?php

namespace App\Http\Requests\PublicTicket;

use App\Models\Event;
use App\Support\BusinessMatchingValidation;
use App\Support\CustomFieldValidation;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StorePublicTicketOrderRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.ticket_id' => ['required', 'integer', 'exists:tickets,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.ticket_session_id' => ['nullable', 'integer', 'exists:ticket_sessions,id'],
            'items.*.selected_event_day_id' => ['nullable', 'integer', 'exists:event_days,id'],

            'buyer_name' => ['required', 'string', 'max:255'],
            'buyer_email' => ['required', 'email', 'max:255'],
            'buyer_phone' => ['required', 'string', 'max:50'],

            'also_attending' => ['sometimes', 'boolean'],
            'promo_code' => ['nullable', 'string', 'max:60'],
            'access_code' => ['nullable', 'string', 'max:60'],
            'accept_terms' => ['accepted'],
            'origin' => ['nullable', 'url', 'max:255'],

            // Business matching intake (buyer answers, stored on their User).
            // Ids resolve by id or legacy_id downstream, so no exists rule here.
            'business_matching' => ['sometimes', 'array'],
            'business_matching.opt_in' => ['sometimes', 'boolean'],
            'business_matching.responses' => ['sometimes', 'array'],
            'business_matching.responses.*.custom_field_id' => ['required_with:business_matching.responses', 'integer'],
            'business_matching.responses.*.value' => ['nullable'],

            // Registration answers (per-attendee; the buyer answers for their
            // own ticket at checkout), keyed by field ulid.
            'registration' => ['sometimes', 'array'],
            'registration.responses' => ['sometimes', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'accept_terms.accepted' => 'You must accept the terms and conditions to continue.',
        ];
    }

    /**
     * Validate business-matching answers per field type + required flag once the
     * base rules pass, reusing the shared Form Builder rule builder.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $event = Event::find($this->input('event_id'));
            if (! $event) {
                return;
            }

            $bm = (array) $this->input('business_matching', []);

            $errors = BusinessMatchingValidation::errorsFor(
                $event,
                (bool) ($bm['opt_in'] ?? false),
                (array) ($bm['responses'] ?? []),
            );

            $registrationFields = $event->registrationFields()->where('is_active', true)->get();

            if ($registrationFields->isNotEmpty()) {
                $errors += CustomFieldValidation::errorsFor(
                    $registrationFields,
                    (array) $this->input('registration.responses', []),
                    'registration.responses',
                );
            }

            foreach ($errors as $key => $message) {
                $validator->errors()->add($key, $message);
            }
        });
    }
}
