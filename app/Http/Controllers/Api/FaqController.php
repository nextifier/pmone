<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFaqRequest;
use App\Http\Requests\UpdateFaqRequest;
use App\Http\Resources\FaqResource;
use App\Models\Event;
use App\Models\Faq;
use App\Models\Project;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\ResponseCache\Facades\ResponseCache;

class FaqController extends Controller
{
    use AuthorizesRequests;

    private function clearFaqCache(): void
    {
        ResponseCache::clear(['faqs']);
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

        $query = $event->faqs()->with(['creator', 'updater']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('question', 'ilike', "%{$search}%")
                    ->orWhere('answer', 'ilike', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $query->orderBy('order_column', 'asc');

        $faqs = $query->get();

        return response()->json([
            'data' => FaqResource::collection($faqs),
            'meta' => [
                'total' => $faqs->count(),
            ],
        ]);
    }

    public function store(StoreFaqRequest $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);
        $this->authorize('create', Faq::class);

        $faq = $event->faqs()->create($request->validated());

        $faq->load(['creator', 'updater']);

        return response()->json([
            'message' => 'FAQ created successfully',
            'data' => new FaqResource($faq),
        ], 201);
    }

    public function show(string $username, string $eventSlug, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);

        $faq = $event->faqs()->with(['creator', 'updater'])->findOrFail($id);

        return response()->json([
            'data' => new FaqResource($faq),
        ]);
    }

    public function update(UpdateFaqRequest $request, string $username, string $eventSlug, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);

        $faq = $event->faqs()->findOrFail($id);
        $this->authorize('update', $faq);

        $faq->update($request->validated());

        $faq->load(['creator', 'updater']);

        return response()->json([
            'message' => 'FAQ updated successfully',
            'data' => new FaqResource($faq),
        ]);
    }

    public function destroy(string $username, string $eventSlug, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);

        $faq = $event->faqs()->findOrFail($id);
        $this->authorize('delete', $faq);
        $faq->delete();

        return response()->json([
            'message' => 'FAQ deleted successfully',
        ]);
    }

    public function trash(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);
        $this->authorize('viewAny', Faq::class);

        $faqs = Faq::onlyTrashed()
            ->where('event_id', $event->id)
            ->with('deleter')
            ->orderByDesc('deleted_at')
            ->get();

        return response()->json([
            'data' => FaqResource::collection($faqs),
            'meta' => [
                'total' => $faqs->count(),
            ],
        ]);
    }

    public function restore(string $username, string $eventSlug, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);

        $faq = Faq::onlyTrashed()
            ->where('event_id', $event->id)
            ->findOrFail($id);

        $this->authorize('restore', $faq);
        $faq->restore();

        $this->clearFaqCache();

        return response()->json([
            'message' => 'FAQ restored successfully',
        ]);
    }

    public function forceDestroy(string $username, string $eventSlug, int $id): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);

        $faq = Faq::onlyTrashed()
            ->where('event_id', $event->id)
            ->findOrFail($id);

        $this->authorize('forceDelete', $faq);
        $faq->forceDelete();

        return response()->json([
            'message' => 'FAQ permanently deleted',
        ]);
    }

    public function bulkDestroy(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);
        $this->authorize('bulkDelete', Faq::class);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['required', 'integer'],
        ]);

        $faqs = Faq::query()
            ->where('event_id', $event->id)
            ->whereIn('id', $validated['ids'])
            ->get();

        $deleted = 0;
        foreach ($faqs as $faq) {
            $faq->delete();
            $deleted++;
        }

        $this->clearFaqCache();

        return response()->json([
            'message' => "{$deleted} FAQ(s) deleted successfully",
            'deleted_count' => $deleted,
        ]);
    }

    public function bulkRestore(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);
        $this->authorize('viewAny', Faq::class);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer'],
        ]);

        $restored = 0;
        foreach ($validated['ids'] as $id) {
            $faq = Faq::onlyTrashed()
                ->where('event_id', $event->id)
                ->find($id);

            if ($faq) {
                $faq->restore();
                $restored++;
            }
        }

        $this->clearFaqCache();

        return response()->json([
            'message' => "{$restored} FAQ(s) restored successfully",
            'restored_count' => $restored,
        ]);
    }

    public function bulkForceDestroy(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);
        $this->authorize('bulkDelete', Faq::class);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer'],
        ]);

        $deleted = 0;
        foreach ($validated['ids'] as $id) {
            $faq = Faq::onlyTrashed()
                ->where('event_id', $event->id)
                ->find($id);

            if ($faq) {
                $faq->forceDelete();
                $deleted++;
            }
        }

        $this->clearFaqCache();

        return response()->json([
            'message' => "{$deleted} FAQ(s) permanently deleted",
            'deleted_count' => $deleted,
        ]);
    }

    public function bulkUpdate(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);
        $this->authorize('bulkUpdate', Faq::class);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['required', 'integer'],
            'is_active' => ['required', 'boolean'],
        ]);

        $updated = Faq::query()
            ->where('event_id', $event->id)
            ->whereIn('id', $validated['ids'])
            ->update([
                'is_active' => $validated['is_active'],
                'updated_by' => auth()->id(),
            ]);

        $this->clearFaqCache();

        return response()->json([
            'message' => "{$updated} FAQ(s) updated successfully",
            'updated_count' => $updated,
        ]);
    }

    public function reorder(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);
        $this->authorize('reorder', Faq::class);

        $validated = $request->validate([
            'orders' => ['required', 'array', 'min:1'],
            'orders.*.id' => ['required', 'integer', 'distinct', 'exists:faqs,id'],
            'orders.*.order' => ['required', 'integer', 'min:1'],
        ]);

        $ids = collect($validated['orders'])->pluck('id')->all();

        $belongCount = Faq::query()
            ->where('event_id', $event->id)
            ->whereIn('id', $ids)
            ->count();

        if ($belongCount !== count($ids)) {
            return response()->json([
                'message' => 'One or more FAQs do not belong to this event.',
            ], 422);
        }

        DB::transaction(function () use ($validated, $event) {
            foreach ($validated['orders'] as $orderData) {
                Faq::where('event_id', $event->id)
                    ->where('id', $orderData['id'])
                    ->update(['order_column' => $orderData['order']]);
            }
        });

        $this->clearFaqCache();

        return response()->json([
            'message' => 'FAQ order updated successfully',
        ]);
    }

    /**
     * List other events in the same project that have FAQ (for the
     * copy-from-event dialog), so a new event can reuse an existing event's FAQ.
     */
    public function sourceEvents(string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);
        $this->authorize('viewAny', Faq::class);

        $events = $project->events()
            ->where('id', '!=', $event->id)
            ->whereHas('faqs')
            ->withCount('faqs')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (Event $e) => [
                'id' => $e->id,
                'title' => $e->title,
                'slug' => $e->slug,
                'faqs_count' => $e->faqs_count,
            ]);

        return response()->json(['data' => $events]);
    }

    /**
     * Copy all FAQ from another event of the same project into this event.
     */
    public function copyFromEvent(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $this->authorize('view', $event);
        $this->authorize('create', Faq::class);

        $validated = $request->validate([
            'source_event_id' => ['required', 'integer', 'exists:events,id'],
        ]);

        $sourceEvent = $project->events()->find($validated['source_event_id']);

        if (! $sourceEvent || $sourceEvent->id === $event->id) {
            return response()->json([
                'message' => 'Source event not found in this project.',
            ], 422);
        }

        $sourceFaqs = $sourceEvent->faqs()->get();

        if ($sourceFaqs->isEmpty()) {
            return response()->json(['message' => 'Source event has no FAQ.'], 422);
        }

        $copied = 0;
        foreach ($sourceFaqs as $sourceFaq) {
            $copy = $sourceFaq->replicate(['order_column', 'created_by', 'updated_by', 'deleted_by']);
            $copy->event_id = $event->id;
            $copy->save();

            if ($sourceFaq->order_column !== null) {
                $copy->order_column = $sourceFaq->order_column;
                $copy->saveQuietly();
            }

            $copied++;
        }

        $this->clearFaqCache();

        return response()->json([
            'message' => "Copied {$copied} FAQ(s) from {$sourceEvent->title}",
            'copied_count' => $copied,
        ]);
    }
}
