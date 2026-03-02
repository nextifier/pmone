<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Http\Resources\EventIndexResource;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\Order;
use App\Models\Project;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EventController extends Controller
{
    use AuthorizesRequests;

    /**
     * Resolve project by username.
     */
    private function resolveProject(string $username): Project
    {
        return Project::where('username', $username)->firstOrFail();
    }

    /**
     * Resolve event by slug within project.
     */
    private function resolveEvent(Project $project, string $eventSlug): Event
    {
        return $project->events()->where('slug', $eventSlug)->firstOrFail();
    }

    public function index(Request $request, string $username): JsonResponse
    {
        $project = $this->resolveProject($username);
        $this->authorize('viewAny', [Event::class, $project]);

        $query = $project->events()->withoutTrashed()
            ->with('project:id,username')
            ->withCount('brandEvents')
            ->withSum([
                'brandEvents as booked_area' => fn ($q) => $q->whereNotNull('booth_size'),
            ], 'booth_size');

        // Search
        if ($request->has('filter.search')) {
            $search = $request->input('filter.search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('location', 'ilike', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('filter.status')) {
            $statuses = explode(',', $request->input('filter.status'));
            $query->whereIn('status', $statuses);
        }

        // Sorting
        $sort = $request->input('sort', 'order_column');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $field = ltrim($sort, '-');
        $query->orderBy($field, $direction);

        $events = $query->paginate($request->input('per_page', 15));

        // Batch load order stats
        $eventIds = collect($events->items())->pluck('id');
        $orderStats = Order::query()
            ->join('brand_event', 'orders.brand_event_id', '=', 'brand_event.id')
            ->whereIn('brand_event.event_id', $eventIds)
            ->whereIn('orders.status', ['submitted', 'confirmed'])
            ->groupBy('brand_event.event_id', 'orders.status')
            ->select(
                'brand_event.event_id',
                'orders.status',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(orders.total) as total_sum')
            )
            ->get();

        $orderStatsMap = [];
        foreach ($orderStats as $stat) {
            $orderStatsMap[$stat->event_id][$stat->status] = [
                'count' => (int) $stat->count,
                'total_sum' => (float) $stat->total_sum,
            ];
        }

        // Decorate events with order stats
        foreach ($events->items() as $event) {
            $eventStats = $orderStatsMap[$event->id] ?? [];
            $event->orders_submitted = $eventStats['submitted']['count'] ?? 0;
            $event->orders_confirmed = $eventStats['confirmed']['count'] ?? 0;
            $event->total_revenue = (float) ($eventStats['confirmed']['total_sum'] ?? 0);
        }

        return response()->json([
            'data' => EventIndexResource::collection($events->items()),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
        ]);
    }

    public function store(StoreEventRequest $request, string $username): JsonResponse
    {
        $project = $this->resolveProject($username);
        $this->authorize('create', [Event::class, $project]);

        $validated = $request->validated();

        $event = new Event($validated);
        $event->project_id = $project->id;
        $event->save();

        // Handle poster image upload
        $this->handleTemporaryUpload($request, $event, 'tmp_poster_image', 'poster_image');

        // Process content images (move from temp to permanent storage)
        $this->processContentImages($event);

        return response()->json([
            'message' => 'Event created successfully',
            'data' => new EventResource($event->load(['creator', 'updater', 'media'])),
        ], 201);
    }

    public function show(string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);

        $this->authorize('view', $event);

        return response()->json([
            'data' => new EventResource($event->load(['creator', 'updater', 'media'])),
        ]);
    }

    public function update(UpdateEventRequest $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);

        $this->authorize('update', $event);

        $oldDescription = $event->description;

        $validated = $request->validated();
        $event->update($validated);

        // Handle poster image upload
        $this->handleTemporaryUpload($request, $event, 'tmp_poster_image', 'poster_image');

        // Process content images (move from temp to permanent storage)
        $this->processContentImages($event);

        // Cleanup removed content images
        $this->cleanupRemovedContentImages($event, $oldDescription);

        return response()->json([
            'message' => 'Event updated successfully',
            'data' => new EventResource($event->load(['creator', 'updater', 'media'])),
        ]);
    }

    public function destroy(string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);

        $this->authorize('delete', $event);

        $event->delete();

        return response()->json([
            'message' => 'Event deleted successfully',
        ]);
    }

    public function updateOrder(Request $request, string $username): JsonResponse
    {
        $project = $this->resolveProject($username);
        $this->authorize('updateOrder', [Event::class, $project]);

        $validated = $request->validate([
            'orders' => ['required', 'array'],
            'orders.*.id' => ['required', 'integer', 'exists:events,id'],
            'orders.*.order' => ['required', 'integer', 'min:1'],
        ]);

        $cases = [];
        $ids = [];
        $params = [];

        foreach ($validated['orders'] as $orderData) {
            $cases[] = 'WHEN id = ? THEN ?::integer';
            $params[] = $orderData['id'];
            $params[] = $orderData['order'];
            $ids[] = $orderData['id'];
        }

        $idsString = implode(',', $ids);
        $casesString = implode(' ', $cases);

        \DB::statement(
            "UPDATE events SET order_column = CASE {$casesString} END WHERE id IN ({$idsString}) AND project_id = ?",
            [...$params, $project->id]
        );

        return response()->json([
            'message' => 'Event order updated successfully',
        ]);
    }

    public function trash(Request $request, string $username): JsonResponse
    {
        $project = $this->resolveProject($username);
        $this->authorize('viewAny', [Event::class, $project]);

        $query = $project->events()->onlyTrashed();

        $events = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => EventIndexResource::collection($events->items()),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
        ]);
    }

    public function restore(string $username, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $project->events()->onlyTrashed()->findOrFail($id);

        $this->authorize('restore', $event);

        $event->restore();

        return response()->json([
            'message' => 'Event restored successfully',
            'data' => new EventResource($event->load(['creator', 'updater', 'media'])),
        ]);
    }

    /**
     * Set an event as the active edition for its project.
     * Deactivates all other events in the same project.
     */
    public function setActive(string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);

        $this->authorize('update', $event);

        // Deactivate all other events in this project
        $project->events()
            ->where('id', '!=', $event->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        // Activate selected event
        $event->update(['is_active' => true]);

        return response()->json([
            'message' => 'Event set as active edition successfully',
            'data' => new EventResource($event->load(['creator', 'updater', 'media'])),
        ]);
    }

    public function forceDestroy(string $username, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $project->events()->onlyTrashed()->findOrFail($id);

        $this->authorize('forceDelete', $event);

        $event->forceDelete();

        return response()->json([
            'message' => 'Event permanently deleted',
        ]);
    }

    /**
     * Handle temporary file upload and move to media collection.
     */
    private function handleTemporaryUpload(Request $request, Event $event, string $fieldName, string $collection): void
    {
        $deleteFieldName = 'delete_'.str_replace('tmp_', '', $fieldName);
        if ($request->has($deleteFieldName) && $request->input($deleteFieldName) === true) {
            $event->clearMediaCollection($collection);

            return;
        }

        if (! $request->has($fieldName)) {
            return;
        }

        $value = $request->input($fieldName);

        if (! $value) {
            return;
        }

        if (! Str::startsWith($value, 'tmp-')) {
            return;
        }

        $metadataPath = "tmp/uploads/{$value}/metadata.json";

        if (! Storage::disk('local')->exists($metadataPath)) {
            return;
        }

        $metadata = json_decode(
            Storage::disk('local')->get($metadataPath),
            true
        );

        $filePath = "tmp/uploads/{$value}/{$metadata['original_name']}";

        if (! Storage::disk('local')->exists($filePath)) {
            return;
        }

        $event->clearMediaCollection($collection);

        $event->addMedia(Storage::disk('local')->path($filePath))
            ->toMediaCollection($collection);

        Storage::disk('local')->deleteDirectory("tmp/uploads/{$value}");
    }

    /**
     * Process content images - move temporary images to permanent storage.
     */
    private function processContentImages(Event $event): void
    {
        if (! $event->description) {
            return;
        }

        $content = $event->description;
        $pattern = '/<img[^>]+src="(?:https?:\/\/[^\/]+)?\/api\/tmp-media\/(tmp-media-[a-zA-Z0-9._-]+)"[^>]*>/';

        if (! preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            return;
        }

        foreach ($matches as $match) {
            $fullImgTag = $match[0];
            $folder = $match[1];

            try {
                $metadataPath = "tmp/uploads/{$folder}/metadata.json";

                if (! Storage::disk('local')->exists($metadataPath)) {
                    continue;
                }

                $metadata = json_decode(Storage::disk('local')->get($metadataPath), true);
                $filename = $metadata['original_name'];
                $tempFilePath = "tmp/uploads/{$folder}/{$filename}";

                if (! Storage::disk('local')->exists($tempFilePath)) {
                    continue;
                }

                $caption = null;
                if (preg_match('/data-caption="([^"]*)"/', $fullImgTag, $captionMatch)) {
                    $caption = html_entity_decode($captionMatch[1]);
                }

                $mediaAdder = $event->addMediaFromDisk($tempFilePath, 'local')
                    ->usingName(pathinfo($filename, PATHINFO_FILENAME));

                if ($caption) {
                    $mediaAdder->withCustomProperties(['caption' => $caption]);
                }

                $media = $mediaAdder->toMediaCollection('description_images');

                $responsiveImg = $this->buildResponsiveImageHtml($media, $caption);
                $content = str_replace($fullImgTag, $responsiveImg, $content);

                Storage::disk('local')->deleteDirectory("tmp/uploads/{$folder}");
            } catch (\Exception $e) {
                logger()->warning('Failed to process content image', [
                    'folder' => $folder,
                    'error' => $e->getMessage(),
                    'event_id' => $event->id,
                ]);
            }
        }

        if ($content !== $event->description) {
            $event->update(['description' => $content]);
        }
    }

    /**
     * Build responsive image HTML with srcset for content images.
     */
    private function buildResponsiveImageHtml($media, ?string $caption = null): string
    {
        $alt = $caption ?? $media->getCustomProperty('caption') ?? $media->name;

        $srcset = [
            $media->getUrl('sm').' 600w',
            $media->getUrl('md').' 900w',
            $media->getUrl('lg').' 1200w',
            $media->getUrl('xl').' 1600w',
        ];

        $srcsetString = implode(', ', $srcset);
        $sizes = '(max-width: 640px) 100vw, (max-width: 1024px) 90vw, 1200px';

        $captionAttr = $caption
            ? sprintf(' data-caption="%s"', htmlspecialchars($caption, ENT_QUOTES, 'UTF-8'))
            : '';

        $html = sprintf(
            '<img src="%s" srcset="%s" sizes="%s" alt="%s"%s loading="lazy" class="w-full h-auto rounded-lg">',
            $media->getUrl('lg'),
            $srcsetString,
            $sizes,
            htmlspecialchars($alt, ENT_QUOTES, 'UTF-8'),
            $captionAttr
        );

        return $html;
    }

    /**
     * Cleanup content images that were removed from event description.
     */
    private function cleanupRemovedContentImages(Event $event, ?string $oldDescription): void
    {
        $contentImages = $event->getMedia('description_images');

        if ($contentImages->isEmpty()) {
            return;
        }

        $currentContent = $event->description ?? '';

        foreach ($contentImages as $media) {
            if (! $this->isMediaUsedInContent($media, $currentContent)) {
                try {
                    $media->delete();
                } catch (\Exception $e) {
                    logger()->warning('Failed to cleanup removed description image', [
                        'event_id' => $event->id,
                        'media_id' => $media->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Check if a media file is used in content.
     */
    private function isMediaUsedInContent($media, string $content): bool
    {
        if (empty($content)) {
            return false;
        }

        $filename = $media->file_name;

        if (str_contains($content, $filename)) {
            return true;
        }

        $encodedFilename = rawurlencode($filename);
        if (str_contains($content, $encodedFilename)) {
            return true;
        }

        $baseName = pathinfo($filename, PATHINFO_FILENAME);
        if (str_contains($content, $baseName)) {
            return true;
        }

        return false;
    }
}
