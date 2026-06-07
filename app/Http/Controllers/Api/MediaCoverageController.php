<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMediaCoverageRequest;
use App\Http\Requests\UpdateMediaCoverageRequest;
use App\Http\Resources\MediaCoverageResource;
use App\Models\Event;
use App\Models\MediaCoverage;
use App\Models\Project;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\ResponseCache\Facades\ResponseCache;

class MediaCoverageController extends Controller
{
    use AuthorizesRequests;

    private function clearCache(): void
    {
        ResponseCache::clear(['media-coverages']);
    }

    private function resolveProject(string $username): Project
    {
        return Project::where('username', $username)->firstOrFail();
    }

    private function resolveEvent(Project $project, string $eventSlug): Event
    {
        return $project->events()->where('slug', $eventSlug)->firstOrFail();
    }

    public function index(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);

        $query = $event->mediaCoverages()->with(['creator', 'updater']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('url', 'ilike', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $query->orderBy('order_column', 'asc');

        $items = $query->get();

        return response()->json([
            'data' => MediaCoverageResource::collection($items),
            'meta' => [
                'total' => $items->count(),
            ],
        ]);
    }

    public function store(StoreMediaCoverageRequest $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);
        $this->authorize('create', MediaCoverage::class);

        $item = $event->mediaCoverages()->create($request->validated());

        $item->load(['creator', 'updater']);

        return response()->json([
            'message' => 'Media coverage created successfully',
            'data' => new MediaCoverageResource($item),
        ], 201);
    }

    public function show(string $username, string $eventSlug, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);

        $item = $event->mediaCoverages()->with(['creator', 'updater'])->findOrFail($id);

        return response()->json([
            'data' => new MediaCoverageResource($item),
        ]);
    }

    public function update(UpdateMediaCoverageRequest $request, string $username, string $eventSlug, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);

        $item = $event->mediaCoverages()->findOrFail($id);
        $this->authorize('update', $item);

        $item->update($request->validated());

        $item->load(['creator', 'updater']);

        return response()->json([
            'message' => 'Media coverage updated successfully',
            'data' => new MediaCoverageResource($item),
        ]);
    }

    public function destroy(string $username, string $eventSlug, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);

        $item = $event->mediaCoverages()->findOrFail($id);
        $this->authorize('delete', $item);
        $item->delete();

        return response()->json([
            'message' => 'Media coverage deleted successfully',
        ]);
    }

    public function trash(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);
        $this->authorize('viewAny', MediaCoverage::class);

        $items = MediaCoverage::onlyTrashed()
            ->where('event_id', $event->id)
            ->with('deleter')
            ->orderByDesc('deleted_at')
            ->get();

        return response()->json([
            'data' => MediaCoverageResource::collection($items),
            'meta' => [
                'total' => $items->count(),
            ],
        ]);
    }

    public function restore(string $username, string $eventSlug, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);

        $item = MediaCoverage::onlyTrashed()
            ->where('event_id', $event->id)
            ->findOrFail($id);

        $this->authorize('restore', $item);
        $item->restore();

        $this->clearCache();

        return response()->json([
            'message' => 'Media coverage restored successfully',
        ]);
    }

    public function forceDestroy(string $username, string $eventSlug, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);

        $item = MediaCoverage::onlyTrashed()
            ->where('event_id', $event->id)
            ->findOrFail($id);

        $this->authorize('forceDelete', $item);
        $item->forceDelete();

        return response()->json([
            'message' => 'Media coverage permanently deleted',
        ]);
    }

    public function bulkDestroy(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);
        $this->authorize('bulkDelete', MediaCoverage::class);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['required', 'integer'],
        ]);

        $items = MediaCoverage::query()
            ->where('event_id', $event->id)
            ->whereIn('id', $validated['ids'])
            ->get();

        $deleted = 0;
        foreach ($items as $item) {
            $item->delete();
            $deleted++;
        }

        $this->clearCache();

        return response()->json([
            'message' => "{$deleted} media coverage(s) deleted successfully",
            'deleted_count' => $deleted,
        ]);
    }

    public function bulkRestore(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);
        $this->authorize('viewAny', MediaCoverage::class);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer'],
        ]);

        $restored = 0;
        foreach ($validated['ids'] as $id) {
            $item = MediaCoverage::onlyTrashed()
                ->where('event_id', $event->id)
                ->find($id);

            if ($item) {
                $item->restore();
                $restored++;
            }
        }

        $this->clearCache();

        return response()->json([
            'message' => "{$restored} media coverage(s) restored successfully",
            'restored_count' => $restored,
        ]);
    }

    public function bulkForceDestroy(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);
        $this->authorize('bulkDelete', MediaCoverage::class);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer'],
        ]);

        $deleted = 0;
        foreach ($validated['ids'] as $id) {
            $item = MediaCoverage::onlyTrashed()
                ->where('event_id', $event->id)
                ->find($id);

            if ($item) {
                $item->forceDelete();
                $deleted++;
            }
        }

        $this->clearCache();

        return response()->json([
            'message' => "{$deleted} media coverage(s) permanently deleted",
            'deleted_count' => $deleted,
        ]);
    }

    public function bulkUpdate(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);
        $this->authorize('bulkUpdate', MediaCoverage::class);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['required', 'integer'],
            'is_active' => ['required', 'boolean'],
        ]);

        $updated = MediaCoverage::query()
            ->where('event_id', $event->id)
            ->whereIn('id', $validated['ids'])
            ->update([
                'is_active' => $validated['is_active'],
                'updated_by' => auth()->id(),
            ]);

        $this->clearCache();

        return response()->json([
            'message' => "{$updated} media coverage(s) updated successfully",
            'updated_count' => $updated,
        ]);
    }

    public function reorder(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);
        $this->authorize('reorder', MediaCoverage::class);

        $validated = $request->validate([
            'orders' => ['required', 'array', 'min:1'],
            'orders.*.id' => ['required', 'integer', 'distinct', 'exists:media_coverages,id'],
            'orders.*.order' => ['required', 'integer', 'min:1'],
        ]);

        $ids = collect($validated['orders'])->pluck('id')->all();

        $belongCount = MediaCoverage::query()
            ->where('event_id', $event->id)
            ->whereIn('id', $ids)
            ->count();

        if ($belongCount !== count($ids)) {
            return response()->json([
                'message' => 'One or more media coverages do not belong to this event.',
            ], 422);
        }

        DB::transaction(function () use ($validated, $event) {
            foreach ($validated['orders'] as $orderData) {
                MediaCoverage::where('event_id', $event->id)
                    ->where('id', $orderData['id'])
                    ->update(['order_column' => $orderData['order']]);
            }
        });

        $this->clearCache();

        return response()->json([
            'message' => 'Media coverage order updated successfully',
        ]);
    }

    /**
     * List other events in the same project that have media coverage (for the
     * copy-from-event dialog).
     */
    public function sourceEvents(string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);
        $this->authorize('viewAny', MediaCoverage::class);

        $events = $project->events()
            ->where('id', '!=', $event->id)
            ->whereHas('mediaCoverages')
            ->withCount('mediaCoverages')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (Event $e) => [
                'id' => $e->id,
                'title' => $e->title,
                'slug' => $e->slug,
                'media_coverages_count' => $e->media_coverages_count,
            ]);

        return response()->json(['data' => $events]);
    }

    /**
     * Copy all media coverage from another event of the same project into this event.
     */
    public function copyFromEvent(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);
        $this->authorize('create', MediaCoverage::class);

        $validated = $request->validate([
            'source_event_id' => ['required', 'integer', 'exists:events,id'],
        ]);

        $sourceEvent = $project->events()->find($validated['source_event_id']);

        if (! $sourceEvent || $sourceEvent->id === $event->id) {
            return response()->json([
                'message' => 'Source event not found in this project.',
            ], 422);
        }

        $sourceItems = $sourceEvent->mediaCoverages()->get();

        if ($sourceItems->isEmpty()) {
            return response()->json(['message' => 'Source event has no media coverage.'], 422);
        }

        $copied = 0;
        foreach ($sourceItems as $sourceItem) {
            $copy = $sourceItem->replicate(['order_column', 'created_by', 'updated_by', 'deleted_by']);
            $copy->event_id = $event->id;
            $copy->save();

            if ($sourceItem->order_column !== null) {
                $copy->order_column = $sourceItem->order_column;
                $copy->saveQuietly();
            }

            $copied++;
        }

        $this->clearCache();

        return response()->json([
            'message' => "Copied {$copied} media coverage(s) from {$sourceEvent->title}",
            'copied_count' => $copied,
        ]);
    }
}
