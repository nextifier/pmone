<?php

namespace App\Http\Requests\EventPublicVisibility;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEventPublicVisibilityRequest extends FormRequest
{
    /**
     * Belt and braces, unlike the ticket-settings sibling which checks only the
     * permission: that lets any holder of `events.update` edit an event in a
     * project they are not a member of. EventPolicy::update() scopes
     * non-master/admin/staff users to project members.
     */
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $event instanceof Event
            && (bool) $this->user()?->can('events.update')
            && (bool) $this->user()?->can('update', $event);
    }

    /**
     * `sometimes` rather than `required`: each switch in the dashboard sends
     * only its own field, so one cannot clobber the other.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'brands_public_visible' => ['sometimes', 'boolean'],
            'rundown_public_visible' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'brands_public_visible.boolean' => 'The brands visibility switch must be true or false.',
            'rundown_public_visible.boolean' => 'The rundown visibility switch must be true or false.',
        ];
    }
}
