<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProgramRequest;
use App\Http\Requests\UpdateProgramRequest;
use App\Http\Resources\ProgramResource;
use App\Models\Event;
use App\Models\Program;
use App\Models\Project;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\ResponseCache\Facades\ResponseCache;

class ProgramController extends Controller
{
    use AuthorizesRequests;

    private function clearProgramCache(): void
    {
        ResponseCache::clear(['programs']);
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

        $query = $event->programs()
            ->with(['media', 'creator', 'updater']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('title', 'ilike', "%{$search}%");
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $query->orderBy('order_column', 'asc');

        $programs = $query->get();

        return response()->json([
            'data' => ProgramResource::collection($programs),
            'meta' => [
                'total' => $programs->count(),
            ],
        ]);
    }

    public function store(StoreProgramRequest $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);
        $this->authorize('create', Program::class);

        $validated = $request->validated();
        $tmpImage = $validated['tmp_image'] ?? null;
        $deleteImage = $validated['delete_image'] ?? false;

        unset($validated['tmp_image'], $validated['delete_image']);

        $program = $event->programs()->create($validated);

        $this->handleTemporaryUpload($tmpImage, $deleteImage, $program, 'image');

        // The trait clear fired on create, BEFORE the image was attached.
        $this->clearProgramCache();

        $program->load(['media', 'creator', 'updater']);

        return response()->json([
            'message' => 'Program created successfully',
            'data' => new ProgramResource($program),
        ], 201);
    }

    public function show(string $username, string $eventSlug, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);

        $program = $event->programs()
            ->with(['media', 'creator', 'updater'])
            ->findOrFail($id);

        return response()->json([
            'data' => new ProgramResource($program),
        ]);
    }

    public function update(UpdateProgramRequest $request, string $username, string $eventSlug, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);

        $program = $event->programs()->findOrFail($id);
        $this->authorize('update', $program);

        $validated = $request->validated();
        $tmpImage = $validated['tmp_image'] ?? null;
        $deleteImage = $validated['delete_image'] ?? false;

        unset($validated['tmp_image'], $validated['delete_image']);

        $program->update($validated);

        $this->handleTemporaryUpload($tmpImage, $deleteImage, $program, 'image');

        // The trait clear fired on $program->update(), BEFORE the image changed.
        $this->clearProgramCache();

        $program->load(['media', 'creator', 'updater']);

        return response()->json([
            'message' => 'Program updated successfully',
            'data' => new ProgramResource($program),
        ]);
    }

    public function destroy(string $username, string $eventSlug, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);

        $program = $event->programs()->findOrFail($id);
        $this->authorize('delete', $program);
        $program->delete();

        return response()->json([
            'message' => 'Program deleted successfully',
        ]);
    }

    public function trash(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);
        $this->authorize('viewAny', Program::class);

        $programs = Program::onlyTrashed()
            ->where('event_id', $event->id)
            ->with(['media', 'deleter'])
            ->orderByDesc('deleted_at')
            ->get();

        return response()->json([
            'data' => ProgramResource::collection($programs),
            'meta' => [
                'total' => $programs->count(),
            ],
        ]);
    }

    public function restore(string $username, string $eventSlug, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);

        $program = Program::onlyTrashed()
            ->where('event_id', $event->id)
            ->findOrFail($id);

        $this->authorize('restore', $program);
        $program->restore();

        $this->clearProgramCache();

        return response()->json([
            'message' => 'Program restored successfully',
        ]);
    }

    public function forceDestroy(string $username, string $eventSlug, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);

        $program = Program::onlyTrashed()
            ->where('event_id', $event->id)
            ->findOrFail($id);

        $this->authorize('forceDelete', $program);
        $program->forceDelete();

        return response()->json([
            'message' => 'Program permanently deleted',
        ]);
    }

    public function bulkDestroy(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);
        $this->authorize('bulkDelete', Program::class);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['required', 'integer'],
        ]);

        $programs = Program::query()
            ->where('event_id', $event->id)
            ->whereIn('id', $validated['ids'])
            ->get();

        $deleted = 0;
        foreach ($programs as $program) {
            $program->delete();
            $deleted++;
        }

        $this->clearProgramCache();

        return response()->json([
            'message' => "{$deleted} program(s) deleted successfully",
            'deleted_count' => $deleted,
        ]);
    }

    public function bulkRestore(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);
        $this->authorize('viewAny', Program::class);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer'],
        ]);

        $restored = 0;
        foreach ($validated['ids'] as $id) {
            $program = Program::onlyTrashed()
                ->where('event_id', $event->id)
                ->find($id);

            if ($program) {
                $program->restore();
                $restored++;
            }
        }

        $this->clearProgramCache();

        return response()->json([
            'message' => "{$restored} program(s) restored successfully",
            'restored_count' => $restored,
        ]);
    }

    public function bulkForceDestroy(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);
        $this->authorize('bulkDelete', Program::class);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer'],
        ]);

        $deleted = 0;
        foreach ($validated['ids'] as $id) {
            $program = Program::onlyTrashed()
                ->where('event_id', $event->id)
                ->find($id);

            if ($program) {
                $program->forceDelete();
                $deleted++;
            }
        }

        $this->clearProgramCache();

        return response()->json([
            'message' => "{$deleted} program(s) permanently deleted",
            'deleted_count' => $deleted,
        ]);
    }

    public function bulkUpdate(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);
        $this->authorize('bulkUpdate', Program::class);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['required', 'integer'],
            'is_active' => ['required', 'boolean'],
        ]);

        $updated = Program::query()
            ->where('event_id', $event->id)
            ->whereIn('id', $validated['ids'])
            ->update([
                'is_active' => $validated['is_active'],
                'updated_by' => auth()->id(),
            ]);

        $this->clearProgramCache();

        return response()->json([
            'message' => "{$updated} program(s) updated successfully",
            'updated_count' => $updated,
        ]);
    }

    public function reorder(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);
        $this->authorize('reorder', Program::class);

        $validated = $request->validate([
            'orders' => ['required', 'array', 'min:1'],
            'orders.*.id' => ['required', 'integer', 'distinct', 'exists:programs,id'],
            'orders.*.order' => ['required', 'integer', 'min:1'],
        ]);

        $ids = collect($validated['orders'])->pluck('id')->all();

        $belongCount = Program::query()
            ->where('event_id', $event->id)
            ->whereIn('id', $ids)
            ->count();

        if ($belongCount !== count($ids)) {
            return response()->json([
                'message' => 'One or more programs do not belong to this event.',
            ], 422);
        }

        DB::transaction(function () use ($validated, $event) {
            foreach ($validated['orders'] as $orderData) {
                Program::where('event_id', $event->id)
                    ->where('id', $orderData['id'])
                    ->update(['order_column' => $orderData['order']]);
            }
        });

        $this->clearProgramCache();

        return response()->json([
            'message' => 'Program order updated successfully',
        ]);
    }

    private function handleTemporaryUpload(?string $tmpValue, bool $shouldDelete, Program $program, string $collection): void
    {
        if ($shouldDelete) {
            $program->clearMediaCollection($collection);

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

        $program->clearMediaCollection($collection);
        $program->addMedia(Storage::disk('local')->path($filePath))->toMediaCollection($collection);

        Storage::disk('local')->deleteDirectory("tmp/uploads/{$tmpValue}");
    }
}
