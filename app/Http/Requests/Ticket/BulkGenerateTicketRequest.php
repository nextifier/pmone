<?php

namespace App\Http\Requests\Ticket;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkGenerateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tickets.bulk_generate') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $eventId = $this->route('event')?->id;

        return [
            'ticket_id' => ['required', 'integer', Rule::exists('tickets', 'id')->where('event_id', $eventId)],
            'ticket_session_id' => ['nullable', 'integer', 'exists:ticket_sessions,id'],
            'selected_event_day_id' => ['nullable', 'integer', 'exists:event_days,id'],
            'mode' => ['required', 'in:anonymous,named'],
            'quantity' => ['required_if:mode,anonymous', 'integer', 'min:1', 'max:5000'],
            'label_prefix' => ['nullable', 'string', 'max:50'],
            'recipients' => ['required_if:mode,named', 'array', 'min:1', 'max:5000'],
            'recipients.*.name' => ['required_with:recipients', 'string', 'max:255'],
            'recipients.*.email' => ['nullable', 'email', 'max:255'],
            'batch_label' => ['nullable', 'string', 'max:120'],
            'reason' => ['nullable', 'string', 'max:500'],
            'delivery' => ['required', 'in:generate_only,auto_email'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('delivery') !== 'auto_email') {
                return;
            }

            if ($this->input('mode') !== 'named') {
                $validator->errors()->add('delivery', 'Auto-email is only available for a named recipient list.');

                return;
            }

            foreach ((array) $this->input('recipients', []) as $i => $recipient) {
                if (empty($recipient['email'])) {
                    $validator->errors()->add("recipients.{$i}.email", 'Every recipient needs an email when auto-emailing.');
                }
            }
        });
    }
}
