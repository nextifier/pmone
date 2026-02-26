<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandEventIndexResource;
use App\Http\Resources\BrandEventResource;
use App\Http\Resources\EventIndexResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\PromotionPostResource;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicProjectController extends Controller
{
    /**
     * Get project detail by username.
     */
    public function show(string $username): JsonResponse
    {
        $project = Project::query()
            ->with(['media', 'links', 'members.media'])
            ->where('username', $username)
            ->active()
            ->firstOrFail();

        return response()->json([
            'data' => new ProjectResource($project),
        ]);
    }

    /**
     * List published events for a project.
     */
    public function events(Request $request, string $username): JsonResponse
    {
        $project = $this->findProject($username);

        $query = Event::query()
            ->with(['media'])
            ->where('project_id', $project->id)
            ->published();

        $this->applyEventFilters($query, $request);
        $this->applyEventSorting($query, $request);

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

    /**
     * Get single event detail.
     */
    public function event(string $username, string $eventSlug): JsonResponse
    {
        $project = $this->findProject($username);

        $event = Event::query()
            ->with(['media'])
            ->where('project_id', $project->id)
            ->where('slug', $eventSlug)
            ->published()
            ->firstOrFail();

        return response()->json([
            'data' => new EventResource($event),
        ]);
    }

    /**
     * List active brands for an event.
     */
    public function brands(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $event = $this->findEvent($username, $eventSlug);

        $query = BrandEvent::query()
            ->with(['brand.media', 'brand.tags'])
            ->where('event_id', $event->id)
            ->where('status', 'active')
            ->ordered();

        if ($search = $request->input('search')) {
            $query->whereHas('brand', function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('company_name', 'ilike', "%{$search}%");
            });
        }

        $brandEvents = $query->paginate($request->input('per_page', 30));

        return response()->json([
            'data' => BrandEventIndexResource::collection($brandEvents->items()),
            'meta' => [
                'current_page' => $brandEvents->currentPage(),
                'last_page' => $brandEvents->lastPage(),
                'per_page' => $brandEvents->perPage(),
                'total' => $brandEvents->total(),
            ],
        ]);
    }

    /**
     * Get single brand detail.
     */
    public function brand(string $username, string $eventSlug, string $brandSlug): JsonResponse
    {
        $event = $this->findEvent($username, $eventSlug);

        $brandEvent = BrandEvent::query()
            ->with(['brand.media', 'brand.tags', 'brand.links'])
            ->where('event_id', $event->id)
            ->where('status', 'active')
            ->whereHas('brand', fn ($q) => $q->where('slug', $brandSlug))
            ->firstOrFail();

        return response()->json([
            'data' => new BrandEventResource($brandEvent),
        ]);
    }

    /**
     * List promotion posts for a brand.
     */
    public function promotionPosts(Request $request, string $username, string $eventSlug, string $brandSlug): JsonResponse
    {
        $event = $this->findEvent($username, $eventSlug);

        $brandEvent = BrandEvent::query()
            ->where('event_id', $event->id)
            ->where('status', 'active')
            ->whereHas('brand', fn ($q) => $q->where('slug', $brandSlug))
            ->firstOrFail();

        $posts = $brandEvent->promotionPosts()
            ->with(['media'])
            ->paginate($request->input('per_page', 30));

        return response()->json([
            'data' => PromotionPostResource::collection($posts->items()),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    /**
     * Find an active project by username.
     */
    private function findProject(string $username): Project
    {
        return Project::query()
            ->where('username', $username)
            ->active()
            ->firstOrFail();
    }

    /**
     * Find a published event within a project.
     */
    private function findEvent(string $username, string $eventSlug): Event
    {
        $project = $this->findProject($username);

        return Event::query()
            ->where('project_id', $project->id)
            ->where('slug', $eventSlug)
            ->published()
            ->firstOrFail();
    }

    /**
     * Get the active event for a project.
     */
    public function activeEvent(string $username): JsonResponse
    {
        $project = $this->findProject($username);

        $event = Event::query()
            ->with(['media'])
            ->where('project_id', $project->id)
            ->where('is_active', true)
            ->published()
            ->firstOrFail();

        return response()->json([
            'data' => new EventResource($event),
        ]);
    }

    /**
     * Apply filters to event query.
     */
    private function applyEventFilters($query, Request $request): void
    {
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('location', 'ilike', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->byStatus($status);
        }
    }

    /**
     * Apply sorting to event query.
     */
    private function applyEventSorting($query, Request $request): void
    {
        $sortField = $request->input('sort', '-start_date');
        $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
        $field = ltrim($sortField, '-');

        $allowedFields = ['title', 'start_date', 'end_date', 'created_at', 'order_column'];

        if (in_array($field, $allowedFields)) {
            $query->orderBy($field, $direction);
        } else {
            $query->orderBy('start_date', 'desc');
        }
    }
}
