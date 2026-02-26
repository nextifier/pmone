<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventIndexResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'project_id' => $this->project_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'edition_number' => $this->edition_number,
            'edition_number_with_ordinal' => $this->edition_number_with_ordinal,
            'date_label' => $this->date_label,
            'start_date' => $this->start_date?->toIso8601String(),
            'end_date' => $this->end_date?->toIso8601String(),
            'location' => $this->location,
            'status' => $this->status,
            'is_active' => $this->is_active,
            'visibility' => $this->visibility,
            'order_column' => $this->order_column,
            'poster_image' => $this->when(
                $this->hasMedia('poster_image'),
                $this->getMediaUrls('poster_image')
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];

        // Include stats when aggregates are loaded (via withCount/withSum)
        if (array_key_exists('brand_events_count', $this->resource->getAttributes())) {
            $data = array_merge($data, [
                'time_status' => $this->computeTimeStatus(),
                'project_username' => $this->resource->relationLoaded('project')
                    ? $this->project?->username
                    : null,
                'brand_events_count' => (int) ($this->brand_events_count ?? 0),
                'gross_area' => (float) ($this->gross_area ?? 0),
                'booked_area' => (float) ($this->booked_area ?? 0),
                'orders_submitted' => (int) ($this->orders_submitted ?? 0),
                'orders_confirmed' => (int) ($this->orders_confirmed ?? 0),
                'total_revenue' => (float) ($this->total_revenue ?? 0),
            ]);
        }

        return $data;
    }

    private function computeTimeStatus(): string
    {
        if (! $this->start_date) {
            return 'no_date';
        }

        $now = now();
        $today = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();
        $endDate = $this->end_date ?? $this->start_date;

        if ($endDate->lt($today)) {
            return 'completed';
        }

        if ($this->start_date->gt($todayEnd)) {
            return 'upcoming';
        }

        return 'ongoing';
    }
}
