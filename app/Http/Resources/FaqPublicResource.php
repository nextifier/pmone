<?php

namespace App\Http\Resources;

use App\Support\FaqTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public resource — resolves the single-locale strings (locale set by the
 * controller) and replaces {{tokens}} with the event/project context via
 * FaqTemplate. Field shape `{ q, a }` matches the pmone-events FAQ.vue.
 *
 * The owning event (with project.links loaded) must be set as the `event`
 * relation by the controller so resolution does not trigger N+1 queries.
 */
class FaqPublicResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $event = $this->event;

        return [
            'q' => $event ? FaqTemplate::render($this->question, $event) : $this->question,
            'a' => $event ? FaqTemplate::render($this->answer, $event) : $this->answer,
        ];
    }
}
