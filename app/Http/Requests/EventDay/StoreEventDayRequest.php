<?php

namespace App\Http\Requests\EventDay;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('event_days.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $eventId = $this->route('event')?->id;
        $ignoreId = $this->route('eventDay')?->id;

        return [
            'day_number' => [
                'required', 'integer', 'min:1',
                Rule::unique('event_days', 'day_number')
                    ->where(fn ($query) => $query->where('event_id', $eventId)->whereNull('deleted_at'))
                    ->ignore($ignoreId),
            ],
            'date' => [
                'required', 'date',
                Rule::unique('event_days', 'date')
                    ->where(fn ($query) => $query->where('event_id', $eventId)->whereNull('deleted_at'))
                    ->ignore($ignoreId),
            ],
            'label' => ['nullable', 'array'],
            'label.en' => ['nullable', 'string', 'max:255'],
            'label.id' => ['nullable', 'string', 'max:255'],
            'label.ja' => ['nullable', 'string', 'max:255'],
            'label.ko' => ['nullable', 'string', 'max:255'],
            'label.zh' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'day_number.unique' => 'This day number already exists for this event.',
            'date.unique' => 'This date is already assigned to another day of this event.',
        ];
    }
}
