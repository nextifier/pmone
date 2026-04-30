<?php

namespace App\Http\Requests;

use App\Models\Event;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRundownItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date', $this->dateRangeRule()],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after_or_equal:start_time'],

            'title' => ['required', 'array'],
            'title.en' => ['required', 'string', 'max:500'],
            'title.id' => ['nullable', 'string', 'max:500'],

            'subtitle' => ['nullable', 'array'],
            'subtitle.en' => ['nullable', 'string', 'max:500'],
            'subtitle.id' => ['nullable', 'string', 'max:500'],

            'description' => ['nullable', 'array'],
            'description.en' => ['nullable', 'string'],
            'description.id' => ['nullable', 'string'],

            'theme' => ['nullable', 'array'],
            'theme.en' => ['nullable', 'string', 'max:500'],
            'theme.id' => ['nullable', 'string', 'max:500'],

            'location' => ['nullable', 'array'],
            'location.en' => ['nullable', 'string', 'max:500'],
            'location.id' => ['nullable', 'string', 'max:500'],

            'presented_by' => ['nullable', 'array'],
            'presented_by.en' => ['nullable', 'string', 'max:255'],
            'presented_by.id' => ['nullable', 'string', 'max:255'],

            'moderator' => ['nullable', 'array'],
            'moderator.en' => ['nullable', 'string', 'max:255'],
            'moderator.id' => ['nullable', 'string', 'max:255'],

            'panelists' => ['nullable', 'array'],
            'panelists.*.name' => ['required_with:panelists', 'string', 'max:255'],
            'panelists.*.title' => ['nullable', 'string', 'max:255'],

            'speakers' => ['nullable', 'array'],
            'speakers.*.name' => ['required_with:speakers', 'string', 'max:255'],
            'speakers.*.title' => ['nullable', 'string', 'max:255'],
            'speakers.*.organization' => ['nullable', 'string', 'max:255'],

            'categories' => ['nullable', 'array'],
            'categories.*' => ['string', 'max:100'],

            'settings' => ['nullable', 'array'],
            'settings.is_group_header' => ['nullable', 'boolean'],
            'more_details' => ['nullable', 'array'],

            'is_active' => ['sometimes', 'boolean'],

            'tmp_poster' => ['nullable', 'string'],
            'poster_delete' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Title is required.',
            'title.en.required' => 'English title is required.',
            'end_time.after_or_equal' => 'End time must be after or equal to start time.',
        ];
    }

    private function dateRangeRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! $value) {
                return;
            }

            $event = $this->resolveEvent();

            if (! $event || ! $event->start_date || ! $event->end_date) {
                return;
            }

            $date = Carbon::parse($value)->startOfDay();
            $start = $event->start_date->copy()->startOfDay();
            $end = $event->end_date->copy()->startOfDay();

            if ($date->lt($start) || $date->gt($end)) {
                $fail("Date must be between {$start->format('Y-m-d')} and {$end->format('Y-m-d')}.");
            }
        };
    }

    private function resolveEvent(): ?Event
    {
        $username = $this->route('username');
        $eventSlug = $this->route('eventSlug');

        if (! $username || ! $eventSlug) {
            return null;
        }

        $project = Project::where('username', $username)->first();

        if (! $project) {
            return null;
        }

        return $project->events()->where('slug', $eventSlug)->first();
    }
}
