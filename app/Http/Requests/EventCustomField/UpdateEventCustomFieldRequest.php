<?php

namespace App\Http\Requests\EventCustomField;

class UpdateEventCustomFieldRequest extends StoreEventCustomFieldRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('event_custom_fields.update') ?? false;
    }
}
