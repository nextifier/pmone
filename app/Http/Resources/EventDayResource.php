<?php

namespace App\Http\Resources;

use App\Models\EventDay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin resource — exposes all locale translations of `label` so the form can
 * edit per-locale.
 *
 * @mixin EventDay
 */
class EventDayResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'day_number' => $this->day_number,
            'date' => $this->date?->toDateString(),
            'label' => $this->getTranslations('label'),
            'is_active' => (bool) $this->is_active,
            'order_column' => $this->order_column,
            'can_edit' => $user ? $user->can('event_days.update') : false,
            'can_delete' => $user ? $user->can('event_days.delete') : false,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
