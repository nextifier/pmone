<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAnnouncementRequest;
use App\Http\Requests\UpdateAnnouncementRequest;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use App\Models\AnnouncementUserDismissal;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AnnouncementController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Announcement::class);

        $query = Announcement::query()
            ->with([
                'creator:id,name,email,username,title',
                'creator.media' => function ($q) {
                    $q->where('collection_name', 'profile_image');
                },
                'media' => function ($q) {
                    $q->where('collection_name', 'image');
                },
            ])
            ->withCount('dismissals');

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        if ($request->boolean('client_only')) {
            $items = $query->get();

            return response()->json([
                'data' => AnnouncementResource::collection($items),
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $items->count(),
                    'total' => $items->count(),
                ],
            ]);
        }

        $items = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => AnnouncementResource::collection($items->items()),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function show(Announcement $announcement): JsonResponse
    {
        $this->authorize('view', $announcement);

        $announcement->load([
            'creator',
            'updater',
            'users:id,name,email',
            'events:id,title,slug',
            'projects:id,name',
            'media',
        ]);

        return response()->json([
            'data' => new AnnouncementResource($announcement),
        ]);
    }

    public function store(StoreAnnouncementRequest $request): JsonResponse
    {
        $this->authorize('create', Announcement::class);

        try {
            $data = $request->validated();
            [$pivots, $payload] = $this->splitPivotsFromPayload($data);

            $announcement = Announcement::create($payload);

            $this->syncTargetingPivots($announcement, $pivots);
            $this->handleImageUpload($request, $announcement);

            $announcement->load([
                'creator',
                'users:id,name,email',
                'events:id,title,slug',
                'projects:id,name',
                'media',
            ]);

            return response()->json([
                'message' => 'Announcement created successfully',
                'data' => new AnnouncementResource($announcement),
            ], 201);
        } catch (\Exception $e) {
            logger()->error('Announcement creation failed', [
                'error' => $e->getMessage(),
                'data' => $request->validated(),
            ]);

            return response()->json([
                'message' => 'Failed to create announcement',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(UpdateAnnouncementRequest $request, Announcement $announcement): JsonResponse
    {
        $this->authorize('update', $announcement);

        try {
            $data = $request->validated();
            [$pivots, $payload] = $this->splitPivotsFromPayload($data);

            $announcement->update($payload);

            $this->syncTargetingPivots($announcement, $pivots);
            $this->handleImageUpload($request, $announcement);

            $announcement->load([
                'creator',
                'updater',
                'users:id,name,email',
                'events:id,title,slug',
                'projects:id,name',
                'media',
            ]);

            return response()->json([
                'message' => 'Announcement updated successfully',
                'data' => new AnnouncementResource($announcement),
            ]);
        } catch (\Exception $e) {
            logger()->error('Announcement update failed', [
                'error' => $e->getMessage(),
                'announcement_id' => $announcement->id,
            ]);

            return response()->json([
                'message' => 'Failed to update announcement',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        $this->authorize('delete', $announcement);

        $announcement->delete();

        return response()->json(['message' => 'Announcement deleted successfully']);
    }

    public function trash(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Announcement::class);

        $query = Announcement::onlyTrashed()
            ->with(['creator', 'deleter', 'media' => fn ($q) => $q->where('collection_name', 'image')])
            ->withCount('dismissals');

        $this->applyFilters($query, $request);

        $items = $query->orderBy('deleted_at', 'desc')->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => AnnouncementResource::collection($items->items()),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function restore(int $id): JsonResponse
    {
        $announcement = Announcement::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $announcement);

        $announcement->restore();

        return response()->json(['message' => 'Announcement restored successfully']);
    }

    public function bulkRestore(Request $request): JsonResponse
    {
        $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);

        $count = Announcement::onlyTrashed()->whereIn('id', $request->ids)->restore();

        return response()->json(['message' => "Restored {$count} announcements"]);
    }

    public function forceDestroy(int $id): JsonResponse
    {
        $announcement = Announcement::onlyTrashed()->findOrFail($id);
        $this->authorize('forceDelete', $announcement);

        $announcement->forceDelete();

        return response()->json(['message' => 'Announcement permanently deleted']);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);
        $this->authorize('deleteAny', Announcement::class);

        $count = Announcement::whereIn('id', $request->ids)->delete();

        return response()->json(['message' => "Deleted {$count} announcements"]);
    }

    public function bulkForceDestroy(Request $request): JsonResponse
    {
        $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);

        $count = 0;
        foreach (Announcement::onlyTrashed()->whereIn('id', $request->ids)->get() as $announcement) {
            $this->authorize('forceDelete', $announcement);
            $announcement->forceDelete();
            $count++;
        }

        return response()->json(['message' => "Permanently deleted {$count} announcements"]);
    }

    /**
     * Public dashboard endpoint — list announcements visible to current user.
     */
    public function forCurrentUser(Request $request): JsonResponse
    {
        $user = $request->user();

        $items = Announcement::query()
            ->visibleTo($user)
            ->with(['media' => fn ($q) => $q->where('collection_name', 'image')])
            ->orderBy('order_column')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => AnnouncementResource::collection($items),
        ]);
    }

    public function dismiss(Request $request, Announcement $announcement): JsonResponse
    {
        $user = $request->user();

        AnnouncementUserDismissal::firstOrCreate(
            ['announcement_id' => $announcement->id, 'user_id' => $user->id],
            ['dismissed_at' => now()]
        );

        return response()->json(['message' => 'Announcement dismissed']);
    }

    private function applyFilters($query, Request $request): void
    {
        if ($search = $request->input('filter_search')) {
            $like = config('database.default') === 'pgsql' ? 'ilike' : 'like';
            $query->where('title', $like, "%{$search}%");
        }

        if ($status = $request->input('filter_status')) {
            $statuses = array_filter(is_array($status) ? $status : explode(',', $status));
            if (count($statuses)) {
                $query->whereIn('status', $statuses);
            }
        }

        if ($type = $request->input('filter_type')) {
            $types = array_filter(is_array($type) ? $type : explode(',', $type));
            if (count($types)) {
                $query->whereIn('type', $types);
            }
        }
    }

    private function applySorting($query, Request $request): void
    {
        $sortBy = $request->input('sort_by', 'order_column');
        $sortDir = $request->input('sort_dir', 'asc');
        $allowed = ['order_column', 'title', 'status', 'type', 'start_time', 'end_time', 'created_at', 'updated_at'];

        if (! in_array($sortBy, $allowed, true)) {
            $sortBy = 'order_column';
        }

        $query->orderBy($sortBy, $sortDir === 'desc' ? 'desc' : 'asc')
            ->orderByDesc('created_at');
    }

    /**
     * @return array{0: array<string, array<int>|null>, 1: array<string, mixed>}
     */
    private function splitPivotsFromPayload(array $data): array
    {
        $pivots = [
            'users' => $data['target_user_ids'] ?? null,
            'events' => $data['target_event_ids'] ?? null,
            'projects' => $data['target_project_ids'] ?? null,
        ];

        unset(
            $data['target_user_ids'],
            $data['target_event_ids'],
            $data['target_project_ids'],
            $data['tmp_image'],
            $data['delete_image'],
        );

        return [$pivots, $data];
    }

    private function syncTargetingPivots(Announcement $announcement, array $pivots): void
    {
        if (is_array($pivots['users'])) {
            $announcement->users()->sync($pivots['users']);
        }
        if (is_array($pivots['events'])) {
            $announcement->events()->sync($pivots['events']);
        }
        if (is_array($pivots['projects'])) {
            $announcement->projects()->sync($pivots['projects']);
        }
    }

    private function handleImageUpload(Request $request, Announcement $announcement): void
    {
        if ($request->boolean('delete_image')) {
            $announcement->clearMediaCollection('image');

            return;
        }

        if (! $request->has('tmp_image')) {
            return;
        }

        $value = $request->input('tmp_image');
        if (! $value || ! Str::startsWith($value, 'tmp-')) {
            return;
        }

        $announcement->clearMediaCollection('image');

        $metadataPath = "tmp/uploads/{$value}/metadata.json";
        if (! Storage::disk('local')->exists($metadataPath)) {
            throw new \Exception("File `{$value}` does not exist");
        }

        $metadata = json_decode(Storage::disk('local')->get($metadataPath), true);
        $filename = $metadata['original_name'];
        $tempFilePath = "tmp/uploads/{$value}/{$filename}";

        $mediaAdder = $announcement->addMediaFromDisk($tempFilePath, 'local')
            ->usingName(pathinfo($filename, PATHINFO_FILENAME));

        $tempFullPath = Storage::disk('local')->path($tempFilePath);
        $imageInfo = @getimagesize($tempFullPath);
        $customProps = [];
        if ($imageInfo) {
            $customProps['width'] = $imageInfo[0];
            $customProps['height'] = $imageInfo[1];
        }
        if ($customProps) {
            $mediaAdder->withCustomProperties($customProps);
        }

        $mediaAdder->toMediaCollection('image');

        Storage::disk('local')->deleteDirectory("tmp/uploads/{$value}");
    }
}
