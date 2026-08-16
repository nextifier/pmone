<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventPublicVisibility\UpdateEventPublicVisibilityRequest;
use App\Jobs\PurgeEdgeCache;
use App\Models\Event;
use App\Support\EdgeCache;
use Illuminate\Http\JsonResponse;

/**
 * Per-event kill switches for the event's public website: the exhibitor list
 * and the rundown. Kept out of the ticket-settings group on purpose - an expo
 * with ticketing off still hides and shows its exhibitor list, and these
 * switches are edited from the Brands and Content screens.
 */
class EventPublicVisibilityController extends Controller
{
    public function show(Event $event): JsonResponse
    {
        return response()->json(['data' => $this->present($event)]);
    }

    public function update(UpdateEventPublicVisibilityRequest $request, Event $event): JsonResponse
    {
        $event->fill($request->validated())->save();

        // No explicit ResponseCache::clear() here: Event::responseCacheTags()
        // already carries 'brands', 'promotion-posts' and 'rundown', and the
        // ClearsResponseCache trait fires on saved(). That is the WHOLE
        // mechanism - never turn this save() into a saveQuietly() or a
        // query-builder update, or the public sites keep serving hidden
        // content for an hour.
        $this->purgeBrandDetailPages($event);

        return response()->json([
            'message' => 'Public visibility updated successfully',
            'data' => $this->present($event->refresh()),
        ]);
    }

    /**
     * The `brands` edge tag maps to the /brands LISTING only
     * (config/edge-sites.php). Brand DETAIL pages are purged through
     * Brand::edgeCachePaths(), and Event implements no edgeCachePaths() at all,
     * so without this an already-visited /brands/{slug} keeps serving from
     * Cloudflare for up to seven days after the list was hidden.
     */
    private function purgeBrandDetailPages(Event $event): void
    {
        if (! $event->wasChanged('brands_public_visible') || ! EdgeCache::isConfigured()) {
            return;
        }

        $paths = $event->brands()
            ->pluck('slug')
            ->filter()
            ->map(fn (string $slug) => "/brands/{$slug}")
            ->values()
            ->all();

        if ($paths === []) {
            return;
        }

        PurgeEdgeCache::dispatch(['brands'], $paths, $event->project?->username)
            ->delay(now()->addSeconds(PurgeEdgeCache::DEBOUNCE_SECONDS));
    }

    /**
     * @return array<string, bool>
     */
    private function present(Event $event): array
    {
        return [
            'brands_public_visible' => (bool) $event->brands_public_visible,
            'rundown_public_visible' => (bool) $event->rundown_public_visible,
        ];
    }
}
