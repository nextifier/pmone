<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormFieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'type' => $this->type,
            'label' => $this->label,
            'placeholder' => $this->placeholder,
            'help_text' => $this->help_text,
            'options' => $this->options,
            'validation' => $this->validation,
            'settings' => $this->settings,
            'order_column' => $this->order_column,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
