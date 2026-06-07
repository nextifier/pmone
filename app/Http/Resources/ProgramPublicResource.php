<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public resource — exposes resolved (single-locale) strings for translatable fields.
 * Locale is resolved via app()->getLocale() set by the controller before transforming.
 *
 * Field shape mirrors what the pmone-events `MainProgramCard` expects:
 * `iconName` (icon mode), `image` (image mode), or neither (text-only mode).
 */
class ProgramPublicResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $image = $this->getMediaUrls('image');
        $description = $this->description;

        return [
            'title' => $this->title,
            'description' => filled($description) ? $description : null,
            'iconName' => $this->icon,
            'image' => $image ? ($image['lg'] ?? $image['md'] ?? $image['original'] ?? null) : null,
        ];
    }
}
