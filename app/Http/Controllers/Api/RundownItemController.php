<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRundownItemRequest;
use App\Http\Requests\UpdateRundownItemRequest;
use App\Http\Resources\RundownItemResource;
use App\Models\Event;
use App\Models\Project;
use App\Models\RundownItem;
use App\Services\Rundown\RundownGrouper;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RundownItemController extends Controller
{
    use AuthorizesRequests;

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

        $query = $event->rundownItems()->with(['media', 'tags']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%")
                    ->orWhere('location', 'ilike', "%{$search}%");
            });
        }

        if ($request->has('date')) {
            $query->where('date', $request->input('date'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $items = $query->get();

        return response()->json([
            'data' => [
                'days' => RundownGrouper::group(
                    $items,
                    fn ($item) => (new RundownItemResource($item))->resolve(),
                    event: $event,
                    unscheduledLabel: 'Unscheduled',
                ),
            ],
        ]);
    }

    public function store(StoreRundownItemRequest $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('update', $event);

        $validated = $request->validated();
        $categories = $validated['categories'] ?? null;
        $tmpPoster = $validated['tmp_poster'] ?? null;
        $posterDelete = $validated['poster_delete'] ?? false;
        unset($validated['categories'], $validated['tmp_poster'], $validated['poster_delete']);

        $item = $event->rundownItems()->create($validated);

        if ($categories !== null) {
            $item->syncTagsWithType($categories, 'rundown_category');
        }

        $this->handleTemporaryUpload($tmpPoster, $posterDelete, $item, 'poster');
        $item->load(['media', 'tags']);

        return response()->json([
            'message' => 'Rundown item created successfully',
            'data' => new RundownItemResource($item),
        ], 201);
    }

    public function show(string $username, string $eventSlug, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);

        $item = $event->rundownItems()->with(['media', 'tags'])->findOrFail($id);

        return response()->json([
            'data' => new RundownItemResource($item),
        ]);
    }

    public function update(UpdateRundownItemRequest $request, string $username, string $eventSlug, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('update', $event);

        $item = $event->rundownItems()->findOrFail($id);

        $validated = $request->validated();
        $categories = $validated['categories'] ?? null;
        $tmpPoster = $validated['tmp_poster'] ?? null;
        $posterDelete = $validated['poster_delete'] ?? false;
        $hasCategories = array_key_exists('categories', $validated);
        unset($validated['categories'], $validated['tmp_poster'], $validated['poster_delete']);

        $item->update($validated);

        if ($hasCategories) {
            $item->syncTagsWithType($categories ?? [], 'rundown_category');
        }

        $this->handleTemporaryUpload($tmpPoster, $posterDelete, $item, 'poster');
        $item->load(['media', 'tags']);

        return response()->json([
            'message' => 'Rundown item updated successfully',
            'data' => new RundownItemResource($item),
        ]);
    }

    public function destroy(string $username, string $eventSlug, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('update', $event);

        $item = $event->rundownItems()->findOrFail($id);
        $item->delete();

        return response()->json([
            'message' => 'Rundown item deleted successfully',
        ]);
    }

    public function trash(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('update', $event);

        $query = RundownItem::onlyTrashed()
            ->with(['media', 'tags', 'deleter'])
            ->where('event_id', $event->id);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        $query->orderByDesc('deleted_at');

        $items = $query->paginate($request->input('per_page', 30));

        return response()->json([
            'data' => RundownItemResource::collection($items->items()),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function restore(string $username, string $eventSlug, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('update', $event);

        $item = RundownItem::onlyTrashed()
            ->where('event_id', $event->id)
            ->findOrFail($id);

        $item->restore();

        return response()->json([
            'message' => 'Rundown item restored successfully',
        ]);
    }

    public function forceDestroy(string $username, string $eventSlug, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('update', $event);

        $item = RundownItem::onlyTrashed()
            ->where('event_id', $event->id)
            ->findOrFail($id);

        $item->forceDelete();

        return response()->json([
            'message' => 'Rundown item permanently deleted',
        ]);
    }

    public function reorder(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('update', $event);

        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'orders' => ['required', 'array', 'min:1'],
            'orders.*.id' => ['required', 'integer', 'distinct', 'exists:rundown_items,id'],
            'orders.*.order' => ['required', 'integer', 'min:1'],
        ]);

        $ids = collect($validated['orders'])->pluck('id')->all();

        $belongCount = RundownItem::query()
            ->where('event_id', $event->id)
            ->whereIn('id', $ids)
            ->count();

        if ($belongCount !== count($ids)) {
            return response()->json([
                'message' => 'One or more items do not belong to this event.',
            ], 422);
        }

        DB::transaction(function () use ($validated, $event) {
            foreach ($validated['orders'] as $orderData) {
                $item = RundownItem::where('event_id', $event->id)
                    ->where('id', $orderData['id'])
                    ->first();

                if ($item) {
                    $item->order_column = $orderData['order'];
                    $item->save();
                }
            }
        });

        return response()->json([
            'message' => 'Rundown order updated successfully',
        ]);
    }

    private function handleTemporaryUpload(?string $tmpValue, bool $shouldDelete, RundownItem $item, string $collection): void
    {
        if ($shouldDelete) {
            $item->clearMediaCollection($collection);

            return;
        }

        if (! $tmpValue || ! Str::startsWith($tmpValue, 'tmp-')) {
            return;
        }

        $metadataPath = "tmp/uploads/{$tmpValue}/metadata.json";

        if (! Storage::disk('local')->exists($metadataPath)) {
            return;
        }

        $metadata = json_decode(Storage::disk('local')->get($metadataPath), true);
        $filePath = "tmp/uploads/{$tmpValue}/{$metadata['original_name']}";

        if (! Storage::disk('local')->exists($filePath)) {
            return;
        }

        $item->clearMediaCollection($collection);
        $item->addMedia(Storage::disk('local')->path($filePath))->toMediaCollection($collection);

        Storage::disk('local')->deleteDirectory("tmp/uploads/{$tmpValue}");
    }
}
