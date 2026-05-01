<?php

namespace App\Http\Controllers\Api;

use App\Helpers\LinkNormalizer;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGuestRequest;
use App\Http\Requests\UpdateGuestRequest;
use App\Http\Resources\GuestResource;
use App\Jobs\BulkSoftDeleteGuests;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Project;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GuestController extends Controller
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

        $query = $event->guests()
            ->with(['media', 'tags', 'links', 'creator', 'updater']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('organization', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('visibility')) {
            $query->where('visibility', $request->input('visibility'));
        }

        if ($request->has('is_featured')) {
            $query->where('is_featured', $request->boolean('is_featured'));
        }

        $sort = $request->input('sort', 'order_column');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $field = ltrim($sort, '-');
        $allowedSorts = ['order_column', 'name', 'organization', 'created_at', 'updated_at'];

        if (in_array($field, $allowedSorts, true)) {
            $query->orderBy($field, $direction);
        } else {
            $query->orderBy('order_column', 'asc');
        }

        $perPage = (int) $request->input('per_page', 30);
        $items = $query->paginate($perPage);

        return response()->json([
            'data' => GuestResource::collection($items->items()),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function store(StoreGuestRequest $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('update', $event);

        $validated = $request->validated();
        $tags = $validated['tags'] ?? null;
        $links = $validated['links'] ?? null;
        $tmpProfileImage = $validated['tmp_profile_image'] ?? null;
        $deleteProfileImage = $validated['delete_profile_image'] ?? false;

        unset(
            $validated['tags'],
            $validated['links'],
            $validated['tmp_profile_image'],
            $validated['delete_profile_image'],
        );

        $guest = $event->guests()->create($validated);

        if ($tags !== null) {
            $guest->syncTagsWithType($tags, 'guest_topic');
        }

        $this->syncLinks($guest, $links);
        $this->handleTemporaryUpload($tmpProfileImage, $deleteProfileImage, $guest, 'profile_image');

        $guest->load(['media', 'tags', 'links', 'creator', 'updater']);

        return response()->json([
            'message' => 'Guest created successfully',
            'data' => new GuestResource($guest),
        ], 201);
    }

    public function show(string $username, string $eventSlug, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);

        $guest = $event->guests()
            ->with(['media', 'tags', 'links', 'creator', 'updater'])
            ->findOrFail($id);

        return response()->json([
            'data' => new GuestResource($guest),
        ]);
    }

    public function update(UpdateGuestRequest $request, string $username, string $eventSlug, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('update', $event);

        $guest = $event->guests()->findOrFail($id);

        $validated = $request->validated();
        $tags = $validated['tags'] ?? null;
        $hasTags = array_key_exists('tags', $validated);
        $links = $validated['links'] ?? null;
        $hasLinks = array_key_exists('links', $validated);
        $tmpProfileImage = $validated['tmp_profile_image'] ?? null;
        $deleteProfileImage = $validated['delete_profile_image'] ?? false;

        unset(
            $validated['tags'],
            $validated['links'],
            $validated['tmp_profile_image'],
            $validated['delete_profile_image'],
        );

        $guest->update($validated);

        if ($hasTags) {
            $guest->syncTagsWithType($tags ?? [], 'guest_topic');
        }

        if ($hasLinks) {
            $this->syncLinks($guest, $links);
        }

        $this->handleTemporaryUpload($tmpProfileImage, $deleteProfileImage, $guest, 'profile_image');

        $guest->load(['media', 'tags', 'links', 'creator', 'updater']);

        return response()->json([
            'message' => 'Guest updated successfully',
            'data' => new GuestResource($guest),
        ]);
    }

    public function destroy(string $username, string $eventSlug, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('update', $event);

        $guest = $event->guests()->findOrFail($id);
        $guest->delete();

        return response()->json([
            'message' => 'Guest deleted successfully',
        ]);
    }

    public function trash(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('update', $event);

        $query = Guest::onlyTrashed()
            ->where('event_id', $event->id)
            ->with(['media', 'tags', 'deleter']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('organization', 'ilike', "%{$search}%");
            });
        }

        $query->orderByDesc('deleted_at');

        $items = $query->paginate($request->input('per_page', 30));

        return response()->json([
            'data' => GuestResource::collection($items->items()),
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

        $guest = Guest::onlyTrashed()
            ->where('event_id', $event->id)
            ->findOrFail($id);

        $guest->restore();

        return response()->json([
            'message' => 'Guest restored successfully',
        ]);
    }

    public function forceDestroy(string $username, string $eventSlug, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('update', $event);

        $guest = Guest::onlyTrashed()
            ->where('event_id', $event->id)
            ->findOrFail($id);

        $guest->forceDelete();

        return response()->json([
            'message' => 'Guest permanently deleted',
        ]);
    }

    public function bulkDestroy(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('update', $event);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer'],
        ]);

        $belongingIds = Guest::query()
            ->where('event_id', $event->id)
            ->whereIn('id', $validated['ids'])
            ->pluck('id')
            ->all();

        if (empty($belongingIds)) {
            return response()->json([
                'message' => 'No matching guests found for this event.',
            ], 422);
        }

        $jobId = Str::uuid()->toString();

        Cache::put("job:{$jobId}", [
            'status' => 'pending',
            'total' => count($belongingIds),
            'processed' => 0,
            'percentage' => 0,
            'message' => 'Preparing to delete guests...',
            'error_message' => null,
        ], now()->addMinutes(30));

        BulkSoftDeleteGuests::dispatch(
            $jobId,
            $belongingIds,
            auth()->id(),
        );

        return response()->json(['job_id' => $jobId]);
    }

    public function bulkRestore(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('update', $event);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer'],
        ]);

        $restored = 0;
        foreach ($validated['ids'] as $id) {
            $guest = Guest::onlyTrashed()
                ->where('event_id', $event->id)
                ->find($id);

            if ($guest) {
                $guest->restore();
                $restored++;
            }
        }

        return response()->json([
            'message' => "{$restored} guest(s) restored successfully",
            'restored_count' => $restored,
        ]);
    }

    public function bulkForceDestroy(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('update', $event);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer'],
        ]);

        $deleted = 0;
        foreach ($validated['ids'] as $id) {
            $guest = Guest::onlyTrashed()
                ->where('event_id', $event->id)
                ->find($id);

            if ($guest) {
                $guest->forceDelete();
                $deleted++;
            }
        }

        return response()->json([
            'message' => "{$deleted} guest(s) permanently deleted",
            'deleted_count' => $deleted,
        ]);
    }

    public function reorder(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('update', $event);

        $validated = $request->validate([
            'orders' => ['required', 'array', 'min:1'],
            'orders.*.id' => ['required', 'integer', 'distinct', 'exists:guests,id'],
            'orders.*.order' => ['required', 'integer', 'min:1'],
        ]);

        $ids = collect($validated['orders'])->pluck('id')->all();

        $belongCount = Guest::query()
            ->where('event_id', $event->id)
            ->whereIn('id', $ids)
            ->count();

        if ($belongCount !== count($ids)) {
            return response()->json([
                'message' => 'One or more guests do not belong to this event.',
            ], 422);
        }

        DB::transaction(function () use ($validated, $event) {
            foreach ($validated['orders'] as $orderData) {
                Guest::where('event_id', $event->id)
                    ->where('id', $orderData['id'])
                    ->update(['order_column' => $orderData['order']]);
            }
        });

        return response()->json([
            'message' => 'Guest order updated successfully',
        ]);
    }

    /**
     * @param  array<int, array{label: string, url: string}>|null  $links
     */
    private function syncLinks(Guest $guest, ?array $links): void
    {
        if ($links === null) {
            return;
        }

        $normalized = LinkNormalizer::normalizeAll($links);
        $guest->links()->delete();

        foreach ($normalized as $index => $linkData) {
            $guest->links()->create([
                'label' => $linkData['label'],
                'url' => $linkData['url'],
                'order' => $index,
            ]);
        }
    }

    private function handleTemporaryUpload(?string $tmpValue, bool $shouldDelete, Guest $guest, string $collection): void
    {
        if ($shouldDelete) {
            $guest->clearMediaCollection($collection);

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

        $guest->clearMediaCollection($collection);
        $guest->addMedia(Storage::disk('local')->path($filePath))->toMediaCollection($collection);

        Storage::disk('local')->deleteDirectory("tmp/uploads/{$tmpValue}");
    }
}
